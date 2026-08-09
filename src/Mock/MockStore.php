<?php namespace ProcessWire\BsProcessEditorial\Mock;

/**
 * Dateibasierter Mock-Speicher für UX-Entwicklung vor dem PW-Adapter.
 */
class MockStore {

	protected string $path;

	public function __construct(?string $path = null) {
		$this->path = $path ?? dirname(__DIR__, 2) . '/data/mock-records.json';
		$this->ensureSeed();
	}

	public function all(): array {
		return $this->read()['records'];
	}

	public function get(string $id): ?array {
		foreach ($this->all() as $record) {
			if ((string) ($record['id'] ?? '') === (string) $id) {
				return $record;
			}
		}
		return null;
	}

	public function save(array $record): array {
		$data = $this->read();
		if (empty($record['id'])) {
			$record['id'] = $this->nextId($data['records']);
			$record['created'] = date('c');
			$data['records'][] = $record;
		} else {
			$found = false;
			foreach ($data['records'] as $i => $existing) {
				if ((string) $existing['id'] === (string) $record['id']) {
					$record['created'] = $existing['created'] ?? date('c');
					$record['modified'] = date('c');
					$data['records'][$i] = $record;
					$found = true;
					break;
				}
			}
			if (!$found) {
				$record['created'] = date('c');
				$record['modified'] = date('c');
				$data['records'][] = $record;
			}
		}
		$this->write($data);
		return $record;
	}

	public function delete(string $id): bool {
		$data = $this->read();
		$before = count($data['records']);
		$data['records'] = array_values(array_filter(
			$data['records'],
			fn($r) => (string) ($r['id'] ?? '') !== (string) $id
		));
		$this->write($data);
		return count($data['records']) < $before;
	}

	protected function ensureSeed(): void {
		if (is_file($this->path)) {
			return;
		}
		$seed = [
			'records' => [
				[
					'id' => '1',
					'title' => 'AWO-Treff Kamp-Lintfort',
					'beschreibung' => 'Begegnungsstätte mit Café und Kursangebot.',
					'aktiv' => true,
					'kategorie' => 'treff',
					'titelbild' => null,
					'ansprechpartner' => [1023],
					'created' => '2026-08-01T10:00:00+02:00',
					'modified' => '2026-08-08T14:30:00+02:00',
				],
				[
					'id' => '2',
					'title' => 'Beratungsstelle Moers',
					'beschreibung' => 'Sozialberatung nach Terminvereinbarung.',
					'aktiv' => true,
					'kategorie' => 'beratung',
					'titelbild' => null,
					'ansprechpartner' => [1024],
					'created' => '2026-08-02T09:00:00+02:00',
					'modified' => '2026-08-07T11:15:00+02:00',
				],
				[
					'id' => '3',
					'title' => 'Kita Sonnenschein',
					'beschreibung' => '',
					'aktiv' => false,
					'kategorie' => 'kita',
					'titelbild' => null,
					'ansprechpartner' => [],
					'created' => '2026-08-03T16:00:00+02:00',
					'modified' => '2026-08-03T16:00:00+02:00',
				],
			],
		];
		$this->write($seed);
	}

	protected function nextId(array $records): string {
		$max = 0;
		foreach ($records as $r) {
			$max = max($max, (int) ($r['id'] ?? 0));
		}
		return (string) ($max + 1);
	}

	protected function read(): array {
		$json = file_get_contents($this->path);
		$data = json_decode($json, true);
		if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) {
			return ['records' => []];
		}
		return $data;
	}

	protected function write(array $data): void {
		$dir = dirname($this->path);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents(
			$this->path,
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		);
	}
}
