/**
 * Editorial UI — Shell-Toggles, Tree, Flash, Form-Validierung, TinyMCE-Helfer.
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
    if (input.classList.contains('bpe-input--html') && window.tinymce) {
      var ed = tinymce.get(input.id);
      if (ed) ed.save();
    }

    var empty = false;
    if (input.type === 'checkbox') {
      empty = !input.checked;
    } else if (input.tagName === 'SELECT' && input.multiple) {
      empty = input.selectedOptions.length === 0;
    } else {
      empty = !String(input.value || '').replace(/<[^>]+>/g, '').trim();
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

  function bindFlashDismiss() {
    document.querySelectorAll('[data-bpe-flash]').forEach(function (flash) {
      var close = flash.querySelector('[data-bpe-flash-close]');
      var hide = function () {
        flash.classList.add('is-hiding');
        window.setTimeout(function () {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 260);
      };
      if (close) close.addEventListener('click', hide);
      window.setTimeout(hide, 5000);
    });
  }

  function bindShell() {
    var app = document.querySelector('.bpe-app');
    if (!app) return;

    document.querySelectorAll('[data-bpe-nav-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        app.setAttribute('data-nav', 'open');
        if (btn.getAttribute('data-bpe-nav-open') === 'foot') {
          var foot = document.querySelector('[data-bpe-nav-foot]');
          if (foot) foot.scrollIntoView({ block: 'nearest' });
        }
      });
    });

    document.querySelectorAll('[data-bpe-nav-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        app.setAttribute('data-nav', 'closed');
      });
    });

    document.querySelectorAll('.bpe-tree__toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.bpe-tree__item');
        if (!item) return;
        var children = item.querySelector(':scope > .bpe-tree__children');
        var open = item.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (children) {
          if (open) children.removeAttribute('hidden');
          else children.setAttribute('hidden', 'hidden');
        }
      });
    });

    document.querySelectorAll('.bpe-tile__action[data-href]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        window.location.href = el.getAttribute('data-href');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindFlashDismiss();
    bindShell();

    var form = document.querySelector('.bpe-form, .bpe-form-daten');
    if (!form) return;

    form.querySelectorAll('.bpe-field').forEach(function (field) {
      var input = field.querySelector('input:not([type="hidden"]), textarea, select');
      if (!input) return;
      input.addEventListener('blur', function () {
        validateField(field);
      });
    });

    form.addEventListener('submit', function (event) {
      if (window.tinymce) tinymce.triggerSave();
      var ok = true;
      form.querySelectorAll('.bpe-field').forEach(function (field) {
        if (!validateField(field)) ok = false;
      });
      if (!ok) event.preventDefault();
    });
  });
})();
