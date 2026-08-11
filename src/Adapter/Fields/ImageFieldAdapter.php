<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeImage;
use ProcessWire\Page;
use ProcessWire\WireUpload;

class ImageFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeImage;
	}

	public function readSchema(Field $field, Page $page): array {
		$extensions = trim((string) $field->get('extensions')) ?: 'gif jpg jpeg png';
		$map = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
		];
		$accept = [];
		foreach (explode(' ', $extensions) as $ext) {
			$ext = strtolower(trim($ext));
			if ($ext === '') {
				continue;
			}
			$accept[] = $map[$ext] ?? ('image/' . $ext);
		}
		$schema = $this->baseSchema($field, 'image');
		$schema['maxFiles'] = (int) $field->get('maxFiles');
		$schema['accept'] = array_values(array_unique($accept));
		return $schema;
	}

	/**
	 * rawValue erwartet:
	 * - ['upload' => true] wenn $_FILES vorhanden (Upload läuft hier)
	 * - ['clear' => true]
	 * - ['keep' => filename]
	 * - null
	 */
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
				$errors[] = 'Seite muss vor dem Bild-Upload angelegt sein.';
				return ['value' => null, 'errors' => $errors];
			}

			$upload = new WireUpload($name);
			$upload->setMaxFiles((int) $field->get('maxFiles') ?: 1);
			$extensions = explode(' ', trim((string) $field->get('extensions')) ?: 'gif jpg jpeg png');
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
			return ['value' => ['action' => 'add', 'files' => $filenames], 'errors' => []];
		}

		if ($field->get('required')) {
			$images = $page->id ? $page->getUnformatted($name) : null;
			$hasExisting = $images && count($images) > 0;
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
		$images = $page->getUnformatted($field->name);

		if ($action === 'clear') {
			$images->deleteAll();
			return;
		}
		if ($action === 'add') {
			if ((int) $field->get('maxFiles') === 1) {
				$images->deleteAll();
			}
			foreach ($sanitizedValue['files'] as $filename) {
				$images->add($page->filesManager()->path() . $filename);
			}
		}
	}

	public function readValue(Field $field, Page $page): mixed {
		$images = $page->getUnformatted($field->name);
		if (!$images || !count($images)) {
			return null;
		}
		$img = $images->first();
		if (!$img) {
			return null;
		}
		$thumb = $img->width(240);
		return [
			'name' => $img->basename,
			'url' => $thumb ? (string) $thumb->url : (string) $img->url,
		];
	}
}
