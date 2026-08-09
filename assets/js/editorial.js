/**
 * Editorial UI — leichtgewichtige Form-Hilfe (Inline-Validierung Pflichtfelder).
 */
(function () {
  'use strict';

  function markInvalid(field, message) {
    field.classList.add('bpe-field--error');
    var existing = field.querySelector('.bpe-field__error');
    if (!existing) {
      existing = document.createElement('p');
      existing.className = 'bpe-field__error';
      existing.setAttribute('role', 'alert');
      field.appendChild(existing);
    }
    existing.textContent = message;
  }

  function clearInvalid(field) {
    field.classList.remove('bpe-field--error');
    var existing = field.querySelector('.bpe-field__error');
    if (existing) existing.remove();
  }

  function validateField(field) {
    var input = field.querySelector('input:not([type="hidden"]), textarea, select');
    if (!input || !input.hasAttribute('required')) return true;

    var empty = false;
    if (input.type === 'checkbox') {
      empty = !input.checked;
    } else if (input.tagName === 'SELECT' && input.multiple) {
      empty = input.selectedOptions.length === 0;
    } else {
      empty = !String(input.value || '').trim();
    }

    if (empty) {
      var label = field.querySelector('.bpe-field__label, .bpe-checkbox__label');
      var name = label ? label.textContent.replace('*', '').trim() : 'Dieses Feld';
      markInvalid(field, '„' + name + '“ ist ein Pflichtfeld.');
      return false;
    }

    clearInvalid(field);
    return true;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.bpe-form');
    if (!form) return;

    form.querySelectorAll('.bpe-field').forEach(function (field) {
      var input = field.querySelector('input:not([type="hidden"]), textarea, select');
      if (!input) return;
      input.addEventListener('blur', function () {
        validateField(field);
      });
    });

    form.addEventListener('submit', function (event) {
      var ok = true;
      form.querySelectorAll('.bpe-field').forEach(function (field) {
        if (!validateField(field)) ok = false;
      });
      if (!ok) event.preventDefault();
    });
  });
})();
