<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeRepeater;
use ProcessWire\Page;
use ProcessWire\Template;

/**
 * Repeater-Feld: Items werden über die injizierten Item-Feld-Adapter gelesen/geschrieben.
 * Item-Identität läuft über ein verstecktes "_itemId" je Zeile — kein Reorder/Sortieren.
 */
class RepeaterFieldAdapter extends AbstractFieldAdapter {

	/** @var AbstractFieldAdapter[] */
	protected array $itemAdapters;

	/** @param AbstractFieldAdapter[] $itemAdapters Adapter für die Felder innerhalb eines Repeater-Items */
	public function __construct(array $itemAdapters) {
		$this->itemAdapters = $itemAdapters;
	}

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeRepeater;
	}

	public function readSchema(Field $field, Page $page): array {
		$itemTemplate = $this->itemTemplate($field);
		$itemFields = [];
		if ($itemTemplate) {
			$itemContext = new Page();
			$itemContext->template = $itemTemplate;
			foreach ($itemTemplate->fields as $subField) {
				$adapter = $this->adapterFor($subField);
				$itemFields[] = $adapter
					? $adapter->readSchema($subField, $itemContext)
					: $this->unsupportedItemSchema($subField);
			}
		}
		$schema = $this->baseSchema($field, 'repeater');
		$schema['itemFields'] = $itemFields;
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$itemTemplate = $this->itemTemplate($field);
		if (!$itemTemplate) {
			return ['value' => null, 'errors' => []];
		}

		$rows = is_array($rawValue) ? $rawValue : [];
		$container = $page->id ? $page->getUnformatted($field->name) : null;
		if ($container) {
			$container->setTrackChanges(true);
		}
		$existingById = [];
		if ($container) {
			foreach ($container as $existingItem) {
				$existingById[$existingItem->id] = $existingItem;
			}
		}

		$prepared = [];
		$errors = [];
		$index = 0;
		foreach ($rows as $rawRow) {
			if (!is_array($rawRow)) {
				continue;
			}
			$itemId = (int) ($rawRow['_itemId'] ?? 0);
			$item = ($itemId && isset($existingById[$itemId])) ? $existingById[$itemId] : null;

			if (!$item) {
				if (!$container) {
					$errors[] = 'Seite muss vor dem Anlegen von Elementen gespeichert sein.';
					break;
				}
				// Neues Item braucht sofort eine gespeicherte Page-ID, sonst schlägt ein
				// Bild-Upload im selben Item fehl (ImageFieldAdapter verlangt $page->id) —
				// gleiches Muster wie die Vorab-Speicherung der Hauptseite in ProcessWireAdapter::saveRecord().
				$item = $container->getNewItem();
				$item->save();
			} else {
				unset($existingById[$itemId]);
				// Items aus dem Container kommen mit aktivierter Output-Formatierung — vor dem
				// Ändern/Speichern von Feldern (z. B. Bild) muss das wie bei jeder Page aus sein.
				$item->of(false);
			}

			$values = [];
			foreach ($itemTemplate->fields as $subField) {
				$adapter = $this->adapterFor($subField);
				if (!$adapter) {
					continue;
				}
				$isImage = $adapter instanceof ImageFieldAdapter;
				$filesBackup = $isImage ? $this->hoistRepeaterFile($subField, $field->name, $index) : null;
				$subRaw = $this->rawForSubField($adapter, $subField, $rawRow);
				$result = $adapter->sanitizeAndValidate($subField, $item, $subRaw);
				if ($isImage) {
					$this->restoreFiles($subField->name, $filesBackup);
				}
				if ($result['errors']) {
					$errors[] = 'Element ' . ($index + 1) . ': ' . $result['errors'][0];
				} else {
					$values[] = ['adapter' => $adapter, 'field' => $subField, 'value' => $result['value']];
				}
			}

			$prepared[] = ['item' => $item, 'values' => $values];
			$index++;
		}

		return [
			'value' => [
				'container' => $container,
				'prepared' => $prepared,
				// Items, die vorher existierten, aber in $rows nicht mehr auftauchen (per "Entfernen" aus dem DOM entfernt)
				'removed' => array_values($existingById),
			],
			'errors' => $errors,
		];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		if (!is_array($sanitizedValue)) {
			return;
		}
		foreach ($sanitizedValue['prepared'] ?? [] as $entry) {
			$item = $entry['item'];
			foreach ($entry['values'] as $v) {
				$v['adapter']->writeValue($v['field'], $item, $v['value']);
			}
			$item->save();
		}
		$container = $sanitizedValue['container'] ?? null;
		foreach ($sanitizedValue['removed'] ?? [] as $item) {
			if ($container) {
				$container->remove($item);
			}
		}
		if ($container) {
			// FieldtypeRepeater::___savePageField() liest beim Speichern die "aktuelle" Feld-Wert-Instanz
			// erneut von der Page — ohne dieses erneute set() sieht es nicht dieselbe (mutierte) Instanz und
			// erkennt entfernte Items nicht als entfernt (getItemsRemoved() bliebe leer).
			$page->set($field->name, $container);
		}
		$page->save($field->name);
	}

	public function readValue(Field $field, Page $page): mixed {
		if (!$page->id) {
			return [];
		}
		$itemTemplate = $this->itemTemplate($field);
		if (!$itemTemplate) {
			return [];
		}
		$rows = [];
		foreach ($page->getUnformatted($field->name) as $item) {
			$row = ['_itemId' => (string) $item->id];
			foreach ($itemTemplate->fields as $subField) {
				$adapter = $this->adapterFor($subField);
				if (!$adapter) {
					continue;
				}
				$row[$subField->name] = $adapter->readValue($subField, $item);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	protected function itemTemplate(Field $field): ?Template {
		$templateId = (int) $field->get('template_id');
		if (!$templateId) {
			return null;
		}
		$tpl = $field->wire()->templates->get($templateId);
		return ($tpl && $tpl->id) ? $tpl : null;
	}

	protected function adapterFor(Field $field): ?AbstractFieldAdapter {
		foreach ($this->itemAdapters as $adapter) {
			if ($adapter->supports($field)) {
				return $adapter;
			}
		}
		return null;
	}

	protected function unsupportedItemSchema(Field $field): array {
		return [
			'name' => $field->name,
			'type' => 'unsupported',
			'label' => (string) $field->getLabel(),
			'pwType' => $this->itemFieldTypeLabel($field),
			'required' => false,
		];
	}

	protected function itemFieldTypeLabel(Field $field): string {
		$type = $field->type;
		if (!$type) {
			return 'unbekannt';
		}
		$info = $type->getModuleInfo();
		$title = trim((string) ($info['title'] ?? ''));
		if ($title !== '') {
			return $title;
		}
		$class = $type->className();
		return str_starts_with($class, 'Fieldtype') ? substr($class, 9) : $class;
	}

	/**
	 * @return mixed Rohwert für den Item-Feld-Adapter.
	 */
	protected function rawForSubField(AbstractFieldAdapter $adapter, Field $subField, array $rawRow): mixed {
		$name = $subField->name;
		if (!($adapter instanceof ImageFieldAdapter)) {
			return $rawRow[$name] ?? null;
		}
		return [
			'clear' => !empty($rawRow[$name . '_clear']),
			'keep' => $rawRow[$name . '_existing'] ?? null,
			'remove' => (array) ($rawRow[$name . '_remove'] ?? []),
			'upload' => !empty($_FILES[$name]['name']),
		];
	}

	/**
	 * PHP liefert Uploads aus geschachtelten Feldnamen (repeaterfeld[idx][bild]) als
	 * $_FILES['repeaterfeld']['name'][idx]['bild'] statt des von WireUpload/ImageFieldAdapter
	 * erwarteten flachen $_FILES['bild']. Hier für die Dauer des sanitizeAndValidate()-Aufrufs
	 * flach zusammenbauen; der Rückgabewert dient restoreFiles() dazu, einen zufällig gleichnamigen
	 * Top-Level-Feld-Eintrag danach wiederherzustellen statt ihn dauerhaft zu überschreiben.
	 *
	 * @return array{0: bool, 1: mixed} [existierte vorher, ursprünglicher Wert]
	 */
	protected function hoistRepeaterFile(Field $subField, string $repeaterName, int $index): array {
		$name = $subField->name;
		$existed = array_key_exists($name, $_FILES);
		$backup = $_FILES[$name] ?? null;

		if (isset($_FILES[$repeaterName]['name'][$index][$name])) {
			$_FILES[$name] = [
				'name' => $_FILES[$repeaterName]['name'][$index][$name] ?? '',
				'type' => $_FILES[$repeaterName]['type'][$index][$name] ?? '',
				'tmp_name' => $_FILES[$repeaterName]['tmp_name'][$index][$name] ?? '',
				'error' => $_FILES[$repeaterName]['error'][$index][$name] ?? UPLOAD_ERR_NO_FILE,
				'size' => $_FILES[$repeaterName]['size'][$index][$name] ?? 0,
			];
		} else {
			unset($_FILES[$name]);
		}

		return [$existed, $backup];
	}

	protected function restoreFiles(string $name, array $backup): void {
		[$existed, $value] = $backup;
		if ($existed) {
			$_FILES[$name] = $value;
		} else {
			unset($_FILES[$name]);
		}
	}
}
