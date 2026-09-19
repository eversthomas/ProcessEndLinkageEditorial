<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeFile;
use ProcessWire\FieldtypeImage;
use ProcessWire\Page;
use ProcessWire\WireUpload;

class FileFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		$type = $field->type;
		return $type instanceof FieldtypeFile && !($type instanceof FieldtypeImage);
	}

	public function readSchema(Field $field, Page $page): array {
		$extensions = trim((string) $field->get('extensions')) ?: 'pdf doc docx zip';
		$accept = [];
		foreach (explode(' ', $extensions) as $ext) {
			$ext = strtolower(trim($ext));
			if ($ext !== '') {
				$accept[] = '.' . $ext;
			}
		}
		$schema = $this->baseSchema($field, 'file');
		$schema['maxFiles'] = (int) $field->get('maxFiles');
		$schema['accept'] = $accept;
		$schema['panel'] = 'sidebar';
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$errors = [];
		$name = $field->name;

		if (is_array($rawValue) && !empty($rawValue['clear'])) {
			return ['value' => ['action' => 'clear'], 'errors' => []];
		}

		$hasUpload = !empty($_FILES[$name]['name']) && (is_string($_FILES[$name]['name'])
			? $_FILES[$name]['name'] !== ''
			: !empty(array_filter((array) $_FILES[$name]['name'])));

		if ($hasUpload) {
			if (!$page->id) {
				$errors[] = 'Seite muss vor dem Datei-Upload angelegt sein.';
				return ['value' => null, 'errors' => $errors];
			}

			$upload = new WireUpload($name);
			$upload->setMaxFiles((int) $field->get('maxFiles') ?: 1);
			$extensions = explode(' ', trim((string) $field->get('extensions')) ?: 'pdf doc docx zip');
			$upload->setValidExtensions($extensions);
			$upload->setOverwrite(false);
			$upload->setDestinationPath($page->filesManager()->path());
			$filenames = $upload->execute();

			if (count($upload->getErrors())) {
				return ['value' => null, 'errors' => $upload->getErrors()];
			}
			if (!$filenames) {
				$errors[] = 'Upload fehlgeschlagen.';
				return ['value' => null, 'errors' => $errors];
			}
			$rollback = [];
			foreach ($filenames as $filename) {
				$rollback[] = ['type' => 'file', 'path' => $page->filesManager()->path() . $filename];
			}
			return ['value' => ['action' => 'add', 'files' => $filenames], 'errors' => [], 'rollback' => $rollback];
		}

		if ($field->get('required')) {
			$files = $page->id ? $page->getUnformatted($name) : null;
			$hasExisting = $files && count($files) > 0;
			$keep = is_array($rawValue) && !empty($rawValue['keep']);
			if (!$hasExisting && !$keep) {
				$errors[] = $this->requiredError($field);
			}
		}

		return ['value' => ['action' => 'keep'], 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		if (!is_array($sanitizedValue)) {
			return;
		}
		$action = $sanitizedValue['action'] ?? 'keep';
		$files = $page->getUnformatted($field->name);

		if ($action === 'clear') {
			$files->deleteAll();
			return;
		}
		if ($action === 'add') {
			if ((int) $field->get('maxFiles') === 1) {
				$files->deleteAll();
			}
			foreach ($sanitizedValue['files'] as $filename) {
				$files->add($page->filesManager()->path() . $filename);
			}
		}
	}

	public function readValue(Field $field, Page $page): mixed {
		$files = $page->getUnformatted($field->name);
		if (!$files || !count($files)) {
			return null;
		}
		$file = $files->first();
		if (!$file) {
			return null;
		}
		return [
			'name' => $file->basename,
			'url' => $file->url,
			'size' => (int) $file->filesize,
		];
	}
}
