<?php namespace ProcessWire\BsProcessEditorial\Auth;

use ProcessWire\BsProcessEditorial;

/**
 * Eigenes Login für Redakteure — getrennt vom PW-Admin-Login (MVP: Mock-Credentials).
 */
class EditorialAuth {

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
	}

	public function isLoggedIn(): bool {
		$user = $this->module->wire()->session->get(BsProcessEditorial::SESSION_KEY);
		return is_array($user) && !empty($user['name']);
	}

	public function userName(): ?string {
		$user = $this->module->wire()->session->get(BsProcessEditorial::SESSION_KEY);
		return is_array($user) ? ($user['name'] ?? null) : null;
	}

	public function attempt(string $username, string $password): bool {
		$expectedUser = (string) $this->module->get('login_user');
		$expectedPass = (string) $this->module->get('login_pass');
		if ($username === $expectedUser && $password === $expectedPass) {
			$this->module->wire()->session->set(BsProcessEditorial::SESSION_KEY, [
				'name' => $username,
				'login_at' => time(),
			]);
			return true;
		}
		return false;
	}

	public function logout(): void {
		$this->module->wire()->session->remove(BsProcessEditorial::SESSION_KEY);
	}
}
