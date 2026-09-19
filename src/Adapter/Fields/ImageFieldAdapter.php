<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

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
		$maxFiles = (int) $field->get('maxFiles');
		$schema = $this->baseSchema($field, 'image');
		$schema['maxFiles'] = $maxFiles;
		$schema['multiple'] = $maxFiles !== 1;
		$schema['accept'] = array_values(array_unique($accept));
		return $schema;
	}

	/**
	 * rawValue erwartet:
	 * - ['clear' => true] (alle Bilder löschen)
	 * - ['keep' => filename] (Single-Bild ohne Änderung behalten)
	 * - ['remove' => string[]] (Dateinamen einzeln entfernen, nur Multi-Bild)
	 * - $_FILES[$field->name] trägt ggf. neue Uploads (Single oder Multi)
	 */
	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$errors = [];
		$name = $field->name;
		$maxFiles = (int) $field->get('maxFiles');
		$multiple = $maxFiles !== 1;

		if (is_array($rawValue) && !empty($rawValue['clear'])) {
			return ['value' => ['action' => 'clear', 'remove' => [], 'add' => []], 'errors' => []];
		}

		$hasUpload = !empty($_FILES[$name]['name']) && (is_string($_FILES[$name]['name'])
			? $_FILES[$name]['name'] !== ''
			: !empty(array_filter((array) $_FILES[$name]['name'])));

		$added = [];
		if ($hasUpload) {
			if (!$page->id) {
				$errors[] = 'Seite muss vor dem Bild-Upload angelegt sein.';
				return ['value' => null, 'errors' => $errors];
			}

			$upload = new WireUpload($name);
			$upload->setMaxFiles($multiple ? $maxFiles : 1);
			$extensions = explode(' ', trim((string) $field->get('extensions')) ?: 'gif jpg jpeg png');
			$upload->setValidExtensions($extensions);
			$upload->setOverwrite(false);
			$upload->setDestinationPath($page->filesManager()->path());
			$added = $upload->execute();

			if (count($upload->getErrors())) {
				return ['value' => null, 'errors' => $upload->getErrors()];
			}
			if (!$added) {
				$errors[] = 'Upload fehlgeschlagen.';
				return ['value' => null, 'errors' => $errors];
			}
		}

		$remove = [];
		if ($multiple && is_array($rawValue) && !empty($rawValue['remove'])) {
			$remove = array_values(array_filter(array_map('strval', (array) $rawValue['remove'])));
		}

		if ($field->get('required')) {
			$images = $page->id ? $page->getUnformatted($name) : null;
			$existingCount = $images ? count($images) : 0;
			$keep = is_array($rawValue) && !empty($rawValue['keep']);
			$satisfied = $multiple
				? (($existingCount - count($remove) + count($added)) > 0)
				: ($existingCount > 0 || $keep || $added);
			if (!$satisfied) {
				$errors[] = $this->requiredError($field);
			}
		}

		if ($errors) {
			return ['value' => null, 'errors' => $errors];
		}

		$rollback = [];
		foreach ($added as $filename) {
			$rollback[] = ['type' => 'file', 'path' => $page->filesManager()->path() . $filename];
		}

		return ['value' => ['action' => 'update', 'remove' => $remove, 'add' => $added], 'errors' => [], 'rollback' => $rollback];
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
		if ($action !== 'update') {
			return;
		}

		$multiple = ((int) $field->get('maxFiles')) !== 1;
		$add = $sanitizedValue['add'] ?? [];

		if (!$multiple) {
			if ($add) {
				$images->deleteAll();
				foreach ($add as $filename) {
					$images->add($page->filesManager()->path() . $filename);
				}
			}
			return;
		}

		foreach ($sanitizedValue['remove'] ?? [] as $filename) {
			$images->remove($filename);
		}
		foreach ($add as $filename) {
			$images->add($page->filesManager()->path() . $filename);
		}
	}

	public function readValue(Field $field, Page $page): mixed {
		$multiple = ((int) $field->get('maxFiles')) !== 1;
		$images = $page->getUnformatted($field->name);

		if (!$multiple) {
			$img = ($images && count($images)) ? $images->first() : null;
			if (!$img) {
				return null;
			}
			$thumb = $img->width(240);
			return [
				'name' => $img->basename,
				'url' => $thumb ? (string) $thumb->url : (string) $img->url,
			];
		}

		$out = [];
		if ($images) {
			foreach ($images as $img) {
				$thumb = $img->width(240);
				$out[] = [
					'name' => $img->basename,
					'url' => $thumb ? (string) $thumb->url : (string) $img->url,
				];
			}
		}
		return $out;
	}
}
