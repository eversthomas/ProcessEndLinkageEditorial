/**
 * Visueller Menü-Builder für Setup → Redaktion.
 * Synchronisiert #Inputfield_editorial_nav (JSON) vor dem Speichern.
 *
 * Ein Template-Knoten im Baum = für die Redaktion freigegeben ("im Baum" = "freigegeben").
 * Darstellung/Daten-Art werden direkt am Knoten gepflegt (mode__{tpl}/datatype__{tpl}
 * als eigenständige <select>-Felder, keine ProcessWire-Inputfields mehr — siehe
 * ProcessEndLinkageEditorial::___execute()). Zusätzlich: "Neue Inhalte hinzufügen"-Bereich
 * (ersetzt die frühere Discovery-Tabelle) und eine schematische Live-Vorschau.
 */
(function () {
  'use strict';

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        if (key === 'className') node.className = attrs[key];
        else if (key === 'text') node.textContent = attrs[key];
        else if (key.indexOf('on') === 0) node.addEventListener(key.slice(2).toLowerCase(), attrs[key]);
        else node.setAttribute(key, attrs[key]);
      });
    }
    (children || []).forEach(function (child) {
      if (child == null) return;
      node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });
    return node;
  }

  function parseData(root) {
    return {
      tree: JSON.parse(root.getAttribute('data-tree') || '[]'),
      templates: JSON.parse(root.getAttribute('data-templates') || '{}'),
      icons: JSON.parse(root.getAttribute('data-icons') || '{}'),
      settings: JSON.parse(root.getAttribute('data-settings') || '{}'),
      candidates: JSON.parse(root.getAttribute('data-candidates') || '[]'),
      datatypes: JSON.parse(root.getAttribute('data-datatypes') || '{}')
    };
  }

  function uid(prefix) {
    return prefix + '-' + Math.random().toString(36).slice(2, 8);
  }

  function ensureDashboard(tree) {
    var has = tree.some(function (n) { return n.type === 'dashboard'; });
    if (!has) {
      tree.unshift({
        id: 'overview',
        type: 'dashboard',
        label: 'Übersicht',
        icon: 'layout-dashboard'
      });
    }
    return tree;
  }

  function collectNodeTemplates(node, out) {
    if (!node) return;
    if (node.type === 'template' && node.template) out.push(node.template);
    (node.children || []).forEach(function (c) { collectNodeTemplates(c, out); });
  }

  function collectTreeTemplates(tree) {
    var out = [];
    tree.forEach(function (node) { collectNodeTemplates(node, out); });
    return out;
  }

  /** @return {Array<{id: string, label: string}>} Section › Gruppe, geordnet nach Baum */
  function collectGroups(tree) {
    var out = [];
    tree.forEach(function (node) {
      if (node.type !== 'section') return;
      (node.children || []).forEach(function (child) {
        if (child.type !== 'group') return;
        out.push({ id: child.id, label: (node.label || node.id) + ' › ' + (child.label || child.id) });
      });
    });
    return out;
  }

  function findGroupById(tree, id) {
    var found = null;
    tree.forEach(function (node) {
      if (found || node.type !== 'section') return;
      (node.children || []).forEach(function (child) {
        if (child.type === 'group' && child.id === id) found = child;
      });
    });
    return found;
  }

  /** Legt bei Bedarf eine Default-Section/-Gruppe an (keine Gruppe im Baum vorhanden). */
  function ensureDefaultGroup(state) {
    var sections = state.tree.filter(function (n) { return n.type === 'section'; });
    var section = sections[0];
    if (!section) {
      section = { id: uid('section'), type: 'section', label: 'Inhalte', icon: 'file-text', children: [] };
      state.tree.push(section);
    }
    if (!Array.isArray(section.children)) section.children = [];
    var groups = section.children.filter(function (n) { return n.type === 'group'; });
    var group = groups[0];
    if (!group) {
      group = { id: uid('group'), type: 'group', label: 'Alle Inhalte', icon: 'folder', children: [] };
      section.children.push(group);
    }
    if (!Array.isArray(group.children)) group.children = [];
    return group;
  }

  function findTextarea() {
    return document.querySelector('#Inputfield_editorial_nav, textarea[name=editorial_nav]');
  }

  function syncJson(tree) {
    var ta = findTextarea();
    if (!ta) return;
    ta.value = JSON.stringify(tree, null, 2);
  }

  function iconSelect(icons, value, onChange) {
    var select = el('select');
    Object.keys(icons).forEach(function (name) {
      var opt = el('option', { value: name, text: icons[name] + ' (' + name + ')' });
      if (name === value) opt.selected = true;
      select.appendChild(opt);
    });
    if (!icons[value]) {
      var custom = el('option', { value: value || 'file-text', text: (value || 'file-text') + ' (custom)' });
      custom.selected = true;
      select.appendChild(custom);
    }
    select.addEventListener('change', function () { onChange(select.value); });
    return select;
  }

  /**
   * Labels für alle aktuell im Baum verwendeten Templates — dynamisch statt aus dem
   * beim Seitenaufruf fixen `state.templates` (das kennt frisch per "+ Hinzufügen"
   * eingehängte Templates noch nicht, siehe collectTreeTemplates()). Fällt für neue
   * Templates auf das Label aus `state.candidates` zurück.
   */
  function templateLabels(state) {
    var map = {};
    collectTreeTemplates(state.tree).forEach(function (name) {
      if (state.templates[name]) {
        map[name] = state.templates[name];
        return;
      }
      var candidate = state.candidates.filter(function (c) { return c.name === name; })[0];
      map[name] = candidate ? (candidate.label || candidate.name) : name;
    });
    return map;
  }

  function templateSelect(templates, value, onChange) {
    var select = el('select');
    var keys = Object.keys(templates);
    if (!keys.length) {
      select.appendChild(el('option', { value: '', text: '— keine freigegeben —' }));
    }
    keys.forEach(function (name) {
      var opt = el('option', { value: name, text: templates[name] });
      if (name === value) opt.selected = true;
      select.appendChild(opt);
    });
    select.addEventListener('change', function () { onChange(select.value); });
    return select;
  }

  /** Darstellung + Daten-Art direkt am Template-Knoten — eigenständige Formularfelder. */
  function settingsSelects(tplName, state) {
    var current = state.settings[tplName] || { mode: 'list', datatype: 'daten' };
    var wrap = el('div', { className: 'bpe-navbuilder__tpl-settings' });

    var modeSelect = el('select', { name: 'mode__' + tplName });
    [['list', 'Liste'], ['single', 'Einzelseite']].forEach(function (pair) {
      var opt = el('option', { value: pair[0], text: pair[1] });
      if (pair[0] === current.mode) opt.selected = true;
      modeSelect.appendChild(opt);
    });
    modeSelect.addEventListener('change', function () {
      state.settings[tplName] = state.settings[tplName] || {};
      state.settings[tplName].mode = modeSelect.value;
    });
    wrap.appendChild(el('label', null, ['Darstellung', modeSelect]));

    var dtSelect = el('select', { name: 'datatype__' + tplName });
    Object.keys(state.datatypes).forEach(function (key) {
      var opt = el('option', { value: key, text: state.datatypes[key] });
      if (key === current.datatype) opt.selected = true;
      dtSelect.appendChild(opt);
    });
    dtSelect.addEventListener('change', function () {
      state.settings[tplName] = state.settings[tplName] || {};
      state.settings[tplName].datatype = dtSelect.value;
    });
    wrap.appendChild(el('label', null, ['Daten-Art', dtSelect]));

    return wrap;
  }

  function move(arr, index, dir) {
    var next = index + dir;
    if (next < 0 || next >= arr.length) return;
    var tmp = arr[index];
    arr[index] = arr[next];
    arr[next] = tmp;
  }

  function render(root, state) {
    var list = root.querySelector('[data-bpe-nav-list]');
    list.innerHTML = '';
    state.tree = ensureDashboard(state.tree);

    state.tree.forEach(function (node, index) {
      list.appendChild(renderTopNode(node, index, state, root));
    });

    renderAddPanel(root, state);

    var previewContainer = root.querySelector('[data-bpe-nav-preview]');
    if (previewContainer) {
      previewContainer.innerHTML = '';
      previewContainer.appendChild(renderPreview(state));
    }

    syncJson(state.tree);
  }

  function actionButtons(onUp, onDown, onRemove) {
    return el('div', { className: 'bpe-navbuilder__actions' }, [
      el('button', { type: 'button', text: '▲', onClick: onUp }),
      el('button', { type: 'button', text: '▼', onClick: onDown }),
      el('button', { type: 'button', text: '✕', onClick: onRemove })
    ]);
  }

  function renderTopNode(node, index, state, root) {
    var card = el('div', {
      className: 'bpe-navbuilder__card' + (node.type === 'dashboard' ? ' bpe-navbuilder__card--dashboard' : '')
    });

    card.appendChild(el('span', {
      className: 'bpe-navbuilder__badge bpe-navbuilder__badge--' + node.type,
      text: node.type === 'dashboard' ? 'Übersicht' : 'Section'
    }));

    var row = el('div', { className: 'bpe-navbuilder__row' });

    var labelInput = el('input', { type: 'text', value: node.label || '' });
    labelInput.addEventListener('input', function () {
      node.label = labelInput.value;
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['Label', labelInput]));

    row.appendChild(el('label', null, [
      'Icon',
      iconSelect(state.icons, node.icon || 'layers', function (v) {
        node.icon = v;
        syncJson(state.tree);
      })
    ]));

    if (node.type === 'section') {
      row.appendChild(el('span', { className: 'bpe-navbuilder__hint', text: 'Erscheint als Hauptpunkt in der linken Leiste der Redaktion.' }));
    }

    row.appendChild(actionButtons(
      function () { move(state.tree, index, -1); render(root, state); },
      function () { move(state.tree, index, 1); render(root, state); },
      function () {
        if (node.type === 'dashboard') {
          window.alert('Die Übersicht sollte erhalten bleiben.');
          return;
        }
        state.tree.splice(index, 1);
        render(root, state);
      }
    ));
    card.appendChild(row);

    var idInput = el('input', { type: 'text', value: node.id || '' });
    idInput.addEventListener('input', function () {
      node.id = idInput.value.trim() || uid(node.type);
      syncJson(state.tree);
    });
    card.appendChild(el('details', { className: 'bpe-navbuilder__advanced' }, [
      el('summary', { text: 'Erweitert' }),
      el('label', null, ['ID', idInput])
    ]));

    if (node.type === 'section') {
      if (!Array.isArray(node.children)) node.children = [];
      var children = el('div', { className: 'bpe-navbuilder__children' });
      node.children.forEach(function (child, cIndex) {
        children.appendChild(renderGroup(child, cIndex, node, state, root));
      });
      children.appendChild(el('button', {
        type: 'button',
        className: 'bpe-navbuilder__addlink',
        text: '+ Gruppe',
        onClick: function () {
          node.children.push({
            id: uid('group'),
            type: 'group',
            label: 'Neue Gruppe',
            icon: 'folder',
            children: []
          });
          render(root, state);
        }
      }));
      card.appendChild(children);
    }

    return card;
  }

  function renderGroup(group, index, section, state, root) {
    if (!Array.isArray(group.children)) group.children = [];
    var box = el('div', { className: 'bpe-navbuilder__group' });
    box.appendChild(el('span', { className: 'bpe-navbuilder__badge bpe-navbuilder__badge--group', text: 'Gruppe' }));

    var row = el('div', { className: 'bpe-navbuilder__row' });

    var labelInput = el('input', { type: 'text', value: group.label || '' });
    labelInput.addEventListener('input', function () {
      group.label = labelInput.value;
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['Label', labelInput]));

    row.appendChild(el('label', null, [
      'Icon',
      iconSelect(state.icons, group.icon || 'folder', function (v) {
        group.icon = v;
        syncJson(state.tree);
      })
    ]));

    row.appendChild(el('span', { className: 'bpe-navbuilder__hint', text: 'Überschrift im Inhaltsbaum der Redaktion.' }));

    row.appendChild(actionButtons(
      function () { move(section.children, index, -1); render(root, state); },
      function () { move(section.children, index, 1); render(root, state); },
      function () {
        section.children.splice(index, 1);
        render(root, state);
      }
    ));
    box.appendChild(row);

    var idInput = el('input', { type: 'text', value: group.id || '' });
    idInput.addEventListener('input', function () {
      group.id = idInput.value.trim() || uid('group');
      syncJson(state.tree);
    });
    box.appendChild(el('details', { className: 'bpe-navbuilder__advanced' }, [
      el('summary', { text: 'Erweitert' }),
      el('label', null, ['ID', idInput])
    ]));

    var tpls = el('div', { className: 'bpe-navbuilder__tpls' });
    var templateNodes = group.children.filter(function (c) { return c.type === 'template'; });
    if (!templateNodes.length) {
      tpls.appendChild(el('p', { className: 'bpe-navbuilder__muted', text: 'Noch keine Templates in dieser Gruppe — neue Inhalte über „Neue Inhalte hinzufügen" unten einhängen.' }));
    }
    templateNodes.forEach(function (tplNode) {
      // map index in group.children
      var realIndex = group.children.indexOf(tplNode);
      tpls.appendChild(renderTemplate(tplNode, realIndex, group, state, root));
    });
    box.appendChild(tpls);
    return box;
  }

  function renderTemplate(node, index, group, state, root) {
    var row = el('div', { className: 'bpe-navbuilder__tpl' });
    row.appendChild(el('label', null, [
      'Template',
      templateSelect(templateLabels(state), node.template || '', function (v) {
        node.template = v;
        if (!node.label || node.label === node.id) {
          node.label = state.templates[v] || v;
        }
        syncJson(state.tree);
        render(root, state);
      })
    ]));

    var labelInput = el('input', { type: 'text', value: node.label || '' });
    labelInput.addEventListener('input', function () {
      node.label = labelInput.value;
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['Label', labelInput]));

    row.appendChild(el('label', null, [
      'Icon',
      iconSelect(state.icons, node.icon || 'file-text', function (v) {
        node.icon = v;
        syncJson(state.tree);
      })
    ]));

    if (node.template) {
      row.appendChild(settingsSelects(node.template, state));
    }

    row.appendChild(actionButtons(
      function () { move(group.children, index, -1); render(root, state); },
      function () { move(group.children, index, 1); render(root, state); },
      function () {
        group.children.splice(index, 1);
        render(root, state);
      }
    ));
    return row;
  }

  /** "Neue Inhalte hinzufügen" — ersetzt die frühere Discovery-Tabelle + Freigabe-AsmSelect. */
  function renderAddPanel(root, state) {
    var container = root.querySelector('[data-bpe-nav-add]');
    if (!container) return;
    container.innerHTML = '';

    var inTree = collectTreeTemplates(state.tree);
    var remaining = state.candidates.filter(function (c) { return inTree.indexOf(c.name) === -1; });

    if (!remaining.length) {
      container.appendChild(el('p', { className: 'bpe-navbuilder__muted', text: 'Alle gefundenen Templates sind bereits freigegeben.' }));
      return;
    }

    var groups = collectGroups(state.tree);
    var table = el('table');
    table.appendChild(el('thead', null, [el('tr', null, [
      el('th', { text: 'Template' }),
      el('th', { text: 'Seiten' }),
      el('th', { text: 'Felder' }),
      el('th', { text: 'Ohne Adapter' }),
      el('th', { text: 'Vorschlag' }),
      el('th', { text: '' })
    ])]));

    var tbody = el('tbody');
    remaining.forEach(function (c) {
      var unsupportedNames = (c.unsupportedFields || []).map(function (u) { return u.name; });
      var unsupportedText = unsupportedNames.length ? unsupportedNames.join(', ') : '—';

      var groupSelect = null;
      if (groups.length) {
        groupSelect = el('select');
        groups.forEach(function (g) {
          groupSelect.appendChild(el('option', { value: g.id, text: g.label }));
        });
      }

      var addBtn = el('button', {
        type: 'button',
        className: 'ui-button ui-widget ui-corner-all',
        text: '+ Hinzufügen',
        onClick: function () {
          var targetGroup = groupSelect ? findGroupById(state.tree, groupSelect.value) : null;
          if (!targetGroup) targetGroup = ensureDefaultGroup(state);
          if (!Array.isArray(targetGroup.children)) targetGroup.children = [];
          targetGroup.children.push({
            id: uid('tpl'),
            type: 'template',
            template: c.name,
            label: c.label || c.name,
            icon: 'file-text'
          });
          state.settings[c.name] = { mode: c.suggestedMode === 'single' ? 'single' : 'list', datatype: 'daten' };
          render(root, state);
        }
      });

      var actionCell = groupSelect ? el('td', null, [groupSelect, addBtn]) : el('td', null, [addBtn]);

      tbody.appendChild(el('tr', null, [
        el('td', { text: (c.label || c.name) + ' (' + c.name + ')' }),
        el('td', { text: String(c.pages != null ? c.pages : '') }),
        el('td', { text: String(c.fields != null ? c.fields : '') }),
        el('td', { text: unsupportedText }),
        el('td', { text: c.kind || '' }),
        actionCell
      ]));
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }

  /** Schematische Live-Vorschau (Struktur/Labels, keine echten Icon-Grafiken/Theme-Farben). */
  function renderPreview(state) {
    var ul = el('ul');
    state.tree.forEach(function (node) {
      ul.appendChild(renderPreviewNode(node));
    });
    return ul;
  }

  function renderPreviewNode(node) {
    if (node.type === 'dashboard') {
      return el('li', { className: 'bpv-dashboard', text: '◆ ' + (node.label || 'Übersicht') });
    }
    if (node.type === 'section') {
      var sLi = el('li', { className: 'bpv-section' });
      sLi.appendChild(el('div', { className: 'bpv-section-label', text: '▸ ' + (node.label || node.id) }));
      var sUl = el('ul');
      (node.children || []).forEach(function (c) { sUl.appendChild(renderPreviewNode(c)); });
      sLi.appendChild(sUl);
      return sLi;
    }
    if (node.type === 'group') {
      var gLi = el('li', { className: 'bpv-group' });
      gLi.appendChild(el('div', { className: 'bpv-group-label', text: node.label || node.id }));
      var gUl = el('ul');
      (node.children || []).forEach(function (c) { gUl.appendChild(renderPreviewNode(c)); });
      gLi.appendChild(gUl);
      return gLi;
    }
    if (node.type === 'template') {
      return el('li', { className: 'bpv-template', text: '· ' + (node.label || node.template || '') });
    }
    return el('li');
  }

  function init() {
    var root = document.getElementById('bpe-navbuilder');
    if (!root) return;
    var data = parseData(root);
    var state = {
      tree: Array.isArray(data.tree) ? data.tree : [],
      templates: data.templates || {},
      icons: data.icons || {},
      settings: data.settings || {},
      candidates: Array.isArray(data.candidates) ? data.candidates : [],
      datatypes: data.datatypes || {}
    };

    root.querySelector('[data-bpe-add-section]').addEventListener('click', function () {
      state.tree.push({
        id: uid('section'),
        type: 'section',
        label: 'Neue Section',
        icon: 'file-text',
        children: []
      });
      render(root, state);
    });

    root.querySelector('[data-bpe-add-dashboard]').addEventListener('click', function () {
      var exists = state.tree.some(function (n) { return n.type === 'dashboard'; });
      if (exists) {
        window.alert('Es gibt bereits eine Übersicht.');
        return;
      }
      state.tree.unshift({
        id: 'overview',
        type: 'dashboard',
        label: 'Übersicht',
        icon: 'layout-dashboard'
      });
      render(root, state);
    });

    var form = root.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        syncJson(state.tree);
      });
    }

    render(root, state);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
