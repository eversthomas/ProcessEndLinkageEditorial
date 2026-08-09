<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeTextarea;
use ProcessWire\Page;

class TextareaFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeTextarea;
	}

	public function readSchema(Field $field, Page $page): array {
		$isHtml = $this->isHtmlField($field);
		$schema = $this->baseSchema($field, $isHtml ? 'html' : 'textarea');
		$schema['placeholder'] = (string) $field->get('placeholder');
		$schema['maxLength'] = (int) ($field->get('maxlength') ?: ($isHtml ? 100000 : 16384));
		$schema['rows'] = (int) ($field->get('rows') ?: ($isHtml ? 14 : 5));
		$schema['contentType'] = (int) $field->get('contentType');
		$schema['html'] = $isHtml;
		$schema['panel'] = 'main';
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$errors = [];
		$sanitizer = $field->wire()->sanitizer;
		$isHtml = $this->isHtmlField($field);
		$maxLength = (int) ($field->get('maxlength') ?: ($isHtml ? 100000 : 16384));
		$raw = (string) ($rawValue ?? '');

		if ($isHtml) {
			if (method_exists($sanitizer, 'purify')) {
				$clean = (string) $sanitizer->purify($raw);
			} else {
				$clean = strip_tags($raw, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><code><pre><span>');
			}
			if (mb_strlen($clean) > $maxLength) {
				$clean = mb_substr($clean, 0, $maxLength);
			}
		} else {
			$clean = $sanitizer->textarea($raw, ['maxLength' => $maxLength]);
		}

		if ($field->get('required') && trim(strip_tags($clean)) === '') {
			$errors[] = $this->requiredError($field);
		}
		return ['value' => $clean, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		return (string) $page->getUnformatted($field->name);
	}

	protected function isHtmlField(Field $field): bool {
		$contentType = (int) $field->get('contentType');
		if ($contentType > 0) {
			return true;
		}
		$inputfieldClass = (string) $field->get('inputfieldClass');
		if ($inputfieldClass !== '' && stripos($inputfieldClass, 'TinyMCE') !== false) {
			return true;
		}
		if ($inputfieldClass !== '' && stripos($inputfieldClass, 'CKEditor') !== false) {
			return true;
		}
		return false;
	}
}
