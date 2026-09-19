<?php namespace ProcessWire\ProcessEndLinkageEditorial\Auth;

use ProcessWire\ProcessEndLinkageEditorial;
use ProcessWire\NullPage;
use ProcessWire\Permission;
use ProcessWire\Role;
use ProcessWire\User;
use ProcessWire\WireException;

/**
 * Eigenes Redaktions-Login — getrennt vom PW-Admin-Login.
 * Authentifiziert gegen PW-User (Passwort), speichert aber nur in SESSION_KEY
 * (kein $session->login(), damit der Admin-Login unberührt bleibt).
 */
class EditorialAuth {

	public const PERMISSION = 'editorial-access';
	public const ROLE = 'editorial';

	/** Rückgabewerte von attempt()/verifyCode(). */
	public const RESULT_OK = 'ok';
	public const RESULT_NEEDS_CODE = 'needs_code';
	public const RESULT_FAILED = 'failed';

	protected ProcessEndLinkageEditorial $module;
	protected ?string $throttleMessage = null;

	public function __construct(ProcessEndLinkageEditorial $module) {
		$this->module = $module;
	}

	/** Grund, warum der letzte attempt() abgelehnt wurde, sofern es ein Rate-Limit war. */
	public function throttleMessage(): ?string {
		return $this->throttleMessage;
	}

	public function isLoggedIn(): bool {
		$data = $this->sessionData();
		if ($data === null) {
			return false;
		}
		if (!empty($data['demo'])) {
			return $this->demoEnabled();
		}
		return $this->currentUser() !== null;
	}

	public function userName(): ?string {
		return $this->displayName();
	}

	public function displayName(): ?string {
		$user = $this->currentUser();
		if ($user) {
			return $user->name;
		}
		$data = $this->sessionData();
		if ($data && !empty($data['demo'])) {
			return (string) ($data['name'] ?? 'demo');
		}
		return null;
	}

	public function currentUser(): ?User {
		$data = $this->sessionData();
		if ($data === null || empty($data['user_id']) || !empty($data['demo'])) {
			return null;
		}

		$user = $this->module->wire()->users->get((int) $data['user_id']);
		if (!$user->id || !$this->canUseEditorial($user)) {
			$this->logout();
			return null;
		}
		return $user;
	}

	public function isDemoSession(): bool {
		$data = $this->sessionData();
		return is_array($data) && !empty($data['demo']);
	}

	public function attempt(string $username, string $password): string {
		$this->throttleMessage = null;
		$username = trim($username);
		$password = (string) $password;
		if ($username === '' || $password === '') {
			return self::RESULT_FAILED;
		}

		$users = $this->module->wire()->users;
		$session = $this->module->wire()->session;
		$sanitizer = $this->module->wire()->sanitizer;

		if (!$this->allowLoginAttempt($username)) {
			return self::RESULT_FAILED;
		}

		$user = $users->get('name=' . $sanitizer->selectorValue($username));

		if ($user->id && $session->authenticate($user, $password) && $this->canUseEditorial($user)) {
			if ($this->requireTfa() && $user->hasTfa()) {
				$this->beginTfaPending($user);
				return self::RESULT_NEEDS_CODE;
			}
			session_regenerate_id(true);
			$session->set(ProcessEndLinkageEditorial::SESSION_KEY, [
				'user_id' => $user->id,
				'name' => $user->name,
				'login_at' => time(),
				'last_seen' => time(),
				'demo' => false,
			]);
			return self::RESULT_OK;
		}

		if ($this->demoEnabled()) {
			$expectedUser = (string) $this->module->get('login_user');
			$expectedPass = (string) $this->module->get('login_pass');
			if ($username === $expectedUser && $password === $expectedPass) {
				session_regenerate_id(true);
				$session->set(ProcessEndLinkageEditorial::SESSION_KEY, [
					'name' => $username,
					'login_at' => time(),
					'last_seen' => time(),
					'demo' => true,
				]);
				return self::RESULT_OK;
			}
		}

		return self::RESULT_FAILED;
	}

	/**
	 * Zweiten Schritt (TOTP-Code) prüfen, nachdem attempt() RESULT_NEEDS_CODE zurückgegeben hat.
	 * Nutzt bewusst dieselbe PW-Bordmittel-API wie der echte Admin-Login (Tfa-Basisklasse),
	 * kein eigener TOTP-Algorithmus.
	 */
	public function verifyCode(string $code): string {
		$this->throttleMessage = null;
		$pending = $this->tfaPendingData();
		if ($pending === null) {
			return self::RESULT_FAILED;
		}

		$username = (string) $pending['name'];
		if (!$this->allowLoginAttempt($username)) {
			return self::RESULT_FAILED;
		}

		$users = $this->module->wire()->users;
		$user = $users->get((int) $pending['user_id']);
		if (!$user->id || $user->name !== $username || !$this->canUseEditorial($user)) {
			$this->cancelTfaPending();
			return self::RESULT_FAILED;
		}

		// hasTfa(true) liefert laut PW-Kern-Doku ggf. eine nicht vollständig initialisierte
		// Instanz — für den eigentlichen Verifikationsaufruf stattdessen $user->tfa_type lesen
		// (liefert die vollständig initialisierte Instanz), hasTfa() nur als "ist aktiviert"-Check.
		if (!$user->hasTfa()) {
			$this->cancelTfaPending();
			return self::RESULT_FAILED;
		}
		$tfaModule = $user->tfa_type;
		if (!$tfaModule || !is_object($tfaModule) || !method_exists($tfaModule, 'isValidUserCode')) {
			// Tfa-Modul wurde zwischen Passwort- und Code-Schritt deinstalliert o. ä. — dieser
			// eine Versuch schlägt fehl, aber nicht dauerhaft: hasTfa() liefert beim nächsten
			// Login-Versuch bereits false, sobald das Modul wirklich weg ist (Selbstheilung).
			$this->cancelTfaPending();
			return self::RESULT_FAILED;
		}

		$settings = $tfaModule->getUserSettings($user);
		$code = trim($code);
		if ($code === '' || !$tfaModule->isValidUserCode($user, $code, $settings)) {
			return self::RESULT_FAILED;
		}

		$this->cancelTfaPending();
		$session = $this->module->wire()->session;
		session_regenerate_id(true);
		$session->set(ProcessEndLinkageEditorial::SESSION_KEY, [
			'user_id' => $user->id,
			'name' => $user->name,
			'login_at' => time(),
			'last_seen' => time(),
			'demo' => false,
		]);
		return self::RESULT_OK;
	}

	public function hasPendingTfa(): bool {
		return $this->tfaPendingData() !== null;
	}

	public function cancelTfaPending(): void {
		$this->module->wire()->session->removeFor('bpe', 'tfa_pending');
	}

	protected function requireTfa(): bool {
		return (bool) $this->module->get('editorial_require_2fa');
	}

	protected function beginTfaPending(User $user): void {
		// Privilegwechsel (Passwort geprüft, Code noch offen) — eigene Session-ID dafür,
		// getrennt vom finalen Regenerate nach erfolgreicher Code-Prüfung.
		session_regenerate_id(true);
		$this->module->wire()->session->setFor('bpe', 'tfa_pending', [
			'user_id' => $user->id,
			'name' => $user->name,
			'started_at' => time(),
		]);
	}

	/** @return array{user_id: int, name: string, started_at: int}|null */
	protected function tfaPendingData(): ?array {
		$session = $this->module->wire()->session;
		$data = $session->getFor('bpe', 'tfa_pending');
		if (!is_array($data)) {
			return null;
		}
		$startedAt = (int) ($data['started_at'] ?? 0);
		if ($startedAt <= 0 || (time() - $startedAt) > 300) {
			$session->removeFor('bpe', 'tfa_pending');
			return null;
		}
		return $data;
	}

	/**
	 * Eigenes, in sich geschlossenes Rate-Limit über WireCache (kein neues DB-Schema).
	 * PW-Core liefert mit SessionLoginThrottle zwar dieselbe Logik, deren Hook feuert aber nur
	 * innerhalb von Session::___login() zuverlässig — für unseren eigenen Login-Endpunkt (der
	 * bewusst nicht über $session->login() geht) ist das Hook-Timing nicht garantiert.
	 * Algorithmus bewusst identisch zu SessionLoginThrottle (bewährt): wachsende Wartezeit je
	 * Fehlversuch, gedeckelt, pro Benutzername (kein IP-Tracking nötig für dieses Szenario).
	 */
	protected function allowLoginAttempt(string $username): bool {
		$cache = $this->module->wire()->cache;
		$seconds = 5;
		$maxSeconds = 60;
		$key = 'bpe-login-throttle-' . $this->module->wire()->sanitizer->pageName($username, true);
		$data = $cache->get($key);
		$attempts = is_array($data) ? (int) ($data['attempts'] ?? 0) : 0;
		$lastAttempt = is_array($data) ? (int) ($data['last'] ?? 0) : 0;
		$now = time();

		if ($attempts > 1) {
			$requireSeconds = min(($attempts - 1) * $seconds, $maxSeconds);
			if (($now - $lastAttempt) < $requireSeconds) {
				$wait = $requireSeconds - ($now - $lastAttempt);
				$this->throttleMessage = "Zu viele Versuche. Bitte {$wait} Sekunde(n) warten und erneut versuchen.";
				return false;
			}
		}

		$attempts++;
		if (($now - $lastAttempt) > $maxSeconds) {
			$attempts = 1;
		}
		$cache->save($key, ['attempts' => $attempts, 'last' => $now], $maxSeconds + 60);
		return true;
	}

	/** Aktivitätszeitpunkt aktualisieren (gleitender Idle-Timeout). Nur wenn bereits eingeloggt. */
	public function touch(): void {
		$session = $this->module->wire()->session;
		$data = $session->get(ProcessEndLinkageEditorial::SESSION_KEY);
		if (is_array($data)) {
			$data['last_seen'] = time();
			$session->set(ProcessEndLinkageEditorial::SESSION_KEY, $data);
		}
	}

	public function logout(): void {
		$this->module->wire()->session->remove(ProcessEndLinkageEditorial::SESSION_KEY);
	}

	public function canUseEditorial(User $user): bool {
		if (!$user->id) {
			return false;
		}
		if ($user->isSuperuser()) {
			return true;
		}
		return $user->hasPermission(self::PERMISSION);
	}

	public function demoEnabled(): bool {
		return (bool) $this->module->get('allow_demo_login');
	}

	/**
	 * Permission + Rolle „editorial“ anlegen (idempotent).
	 */
	public function ensureAccessInfrastructure(): void {
		$permissions = $this->module->wire()->permissions;
		$roles = $this->module->wire()->roles;

		$perm = $permissions->get(self::PERMISSION);
		if (!$perm->id) {
			try {
				/** @var Permission|NullPage $created */
				$created = $permissions->add(self::PERMISSION);
				if ($created->id) {
					$created->title = 'Redaktionsoberfläche nutzen';
					$created->save();
				}
			} catch (WireException $e) {
				// Race / bereits vorhanden
			}
			$perm = $permissions->get(self::PERMISSION);
		}

		$role = $roles->get(self::ROLE);
		if (!$role->id) {
			try {
				/** @var Role|NullPage $role */
				$role = $roles->add(self::ROLE);
			} catch (WireException $e) {
				$role = $roles->get(self::ROLE);
			}
		}
		if ($role->id && $perm->id && !$role->hasPermission($perm)) {
			$role->of(false);
			$role->addPermission($perm);
			$role->save();
		}
	}

	/** @return array<string, mixed>|null */
	protected function sessionData(): ?array {
		$data = $this->module->wire()->session->get(ProcessEndLinkageEditorial::SESSION_KEY);
		if (!is_array($data)) {
			return null;
		}
		if ($this->isTimedOut($data)) {
			$this->logout();
			return null;
		}
		return $data;
	}

	protected function isTimedOut(array $data): bool {
		$timeoutMinutes = (int) $this->module->get('session_timeout_minutes');
		if ($timeoutMinutes <= 0) {
			return false;
		}
		$lastSeen = (int) ($data['last_seen'] ?? $data['login_at'] ?? 0);
		if ($lastSeen <= 0) {
			return false;
		}
		return (time() - $lastSeen) > ($timeoutMinutes * 60);
	}
}
