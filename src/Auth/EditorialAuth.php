<?php namespace ProcessWire\BsProcessEditorial\Auth;

use ProcessWire\BsProcessEditorial;
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

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
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

	public function attempt(string $username, string $password): bool {
		$username = trim($username);
		$password = (string) $password;
		if ($username === '' || $password === '') {
			return false;
		}

		$users = $this->module->wire()->users;
		$session = $this->module->wire()->session;
		$sanitizer = $this->module->wire()->sanitizer;
		$user = $users->get('name=' . $sanitizer->selectorValue($username));

		if ($user->id && $session->authenticate($user, $password) && $this->canUseEditorial($user)) {
			$session->set(BsProcessEditorial::SESSION_KEY, [
				'user_id' => $user->id,
				'name' => $user->name,
				'login_at' => time(),
				'demo' => false,
			]);
			return true;
		}

		if ($this->demoEnabled()) {
			$expectedUser = (string) $this->module->get('login_user');
			$expectedPass = (string) $this->module->get('login_pass');
			if ($username === $expectedUser && $password === $expectedPass) {
				$session->set(BsProcessEditorial::SESSION_KEY, [
					'name' => $username,
					'login_at' => time(),
					'demo' => true,
				]);
				return true;
			}
		}

		return false;
	}

	public function logout(): void {
		$this->module->wire()->session->remove(BsProcessEditorial::SESSION_KEY);
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
		$data = $this->module->wire()->session->get(BsProcessEditorial::SESSION_KEY);
		return is_array($data) ? $data : null;
	}
}
