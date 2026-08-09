/**
 * Visueller Menü-Builder für Setup → Redaktion.
 * Synchronisiert #Inputfield_editorial_nav (JSON) vor dem Speichern.
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
      icons: JSON.parse(root.getAttribute('data-icons') || '{}')
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

    var row = el('div', { className: 'bpe-navbuilder__row' });
    row.appendChild(el('label', null, [
      'Typ',
      el('strong', { text: node.type === 'dashboard' ? 'Übersicht (Dashboard)' : 'Section (Rail)' })
    ]));

    var idInput = el('input', { type: 'text', value: node.id || '' });
    idInput.addEventListener('input', function () {
      node.id = idInput.value.trim() || uid(node.type);
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['ID', idInput]));

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

    if (node.type === 'section') {
      if (!Array.isArray(node.children)) node.children = [];
      var children = el('div', { className: 'bpe-navbuilder__children' });
      node.children.forEach(function (child, cIndex) {
        children.appendChild(renderGroup(child, cIndex, node, state, root));
      });
      children.appendChild(el('button', {
        type: 'button',
        className: 'ui-button ui-widget ui-corner-all',
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
    var row = el('div', { className: 'bpe-navbuilder__row' });

    var labelInput = el('input', { type: 'text', value: group.label || '' });
    labelInput.addEventListener('input', function () {
      group.label = labelInput.value;
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['Gruppe', labelInput]));

    var idInput = el('input', { type: 'text', value: group.id || '' });
    idInput.addEventListener('input', function () {
      group.id = idInput.value.trim() || uid('group');
      syncJson(state.tree);
    });
    row.appendChild(el('label', null, ['ID', idInput]));

    row.appendChild(el('label', null, [
      'Icon',
      iconSelect(state.icons, group.icon || 'folder', function (v) {
        group.icon = v;
        syncJson(state.tree);
      })
    ]));

    row.appendChild(actionButtons(
      function () { move(section.children, index, -1); render(root, state); },
      function () { move(section.children, index, 1); render(root, state); },
      function () {
        section.children.splice(index, 1);
        render(root, state);
      }
    ));
    box.appendChild(row);

    var tpls = el('div', { className: 'bpe-navbuilder__tpls' });
    var templateNodes = group.children.filter(function (c) { return c.type === 'template'; });
    if (!templateNodes.length) {
      tpls.appendChild(el('p', { className: 'bpe-navbuilder__muted', text: 'Noch keine Templates in dieser Gruppe.' }));
    }
    templateNodes.forEach(function (tplNode, tIndex) {
      // map index in group.children
      var realIndex = group.children.indexOf(tplNode);
      tpls.appendChild(renderTemplate(tplNode, realIndex, group, state, root));
    });
    tpls.appendChild(el('button', {
      type: 'button',
      className: 'ui-button ui-widget ui-corner-all',
      text: '+ Template',
      onClick: function () {
        var first = Object.keys(state.templates)[0] || '';
        group.children.push({
          id: uid('tpl'),
          type: 'template',
          template: first,
          label: state.templates[first] || first || 'Template',
          icon: 'file-text'
        });
        render(root, state);
      }
    }));
    box.appendChild(tpls);
    return box;
  }

  function renderTemplate(node, index, group, state, root) {
    var row = el('div', { className: 'bpe-navbuilder__tpl' });
    row.appendChild(el('label', null, [
      'Template',
      templateSelect(state.templates, node.template || '', function (v) {
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

  function init() {
    var root = document.getElementById('bpe-navbuilder');
    if (!root) return;
    var data = parseData(root);
    var state = {
      tree: Array.isArray(data.tree) ? data.tree : [],
      templates: data.templates || {},
      icons: data.icons || {}
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
