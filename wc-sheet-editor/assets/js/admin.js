/* globals WCSE */
(function () {
  const el = document.getElementById("wcse-app");
  if (!el) return;

  const state = { page: 1, pages: 1, rows: [], dirty: new Map(), cats: [], acfFields: [], visible: new Set(Array.isArray(WCSE.visibleFields) ? WCSE.visibleFields : []) };
  let dt = null;
  const STOCK_STATUS = ["instock", "outofstock", "onbackorder"];
  const STATUSES = ["publish", "draft", "pending", "private"];

  const api = (path, args = {}) =>
    window.wp.apiFetch({
      path: path.startsWith("/") ? path : "/" + path,
      ...args,
      headers: { "X-WP-Nonce": WCSE.nonce },
    });

  function render() {
    el.innerHTML = `
      <div class="wcse-toolbar">
        <button id="prev" ${state.page <= 1 ? "disabled" : ""}>◀</button>
        <span>Page ${state.page}/${state.pages}</span>
        <button id="next" ${
          state.page >= state.pages ? "disabled" : ""
        }>▶</button>
        <button id="save" ${state.dirty.size ? "" : "disabled"}>${
      WCSE.i18n.save
    } (${state.dirty.size})</button>
        <button id="columns">${WCSE.i18n.columns || 'Columns'}</button>
      </div>
      ${columnsPanelHtml()}
      <div class="wcse-table-wrap">
        <table id="wcse-table" class="wcse-table display nowrap" style="width:100%">
          <thead><tr>${headerHtml()}</tr></thead>
          <tbody>
          ${state.rows
            .map(
              (r) => `
            <tr data-id="${r.ID}">
              <td data-col="id">${r.ID}</td>
              <td data-col="name" contenteditable data-key="name">${esc(r.name)}</td>
              <td data-col="sku" contenteditable data-key="sku">${esc(r.sku || "")}</td>
              <td data-col="regular_price" contenteditable data-key="regular_price">${esc(r.regular_price || "")}</td>
              <td data-col="sale_price" contenteditable data-key="sale_price">${esc(r.sale_price || "")}</td>
              <td data-col="stock_status">${selectHtml("stock_status", STOCK_STATUS, r.stock_status)}</td>
              <td data-col="stock_qty" contenteditable data-key="stock_qty">${r.stock_qty == null ? "" : esc(r.stock_qty)}</td>
              <td data-col="status">${selectHtml("status", STATUSES, r.status)}</td>
              <td data-col="categories" class="wcse-categories" data-key="categories">${tokensHtml(r.categories)}<input type="text" class="wcse-token-input" list="wcse-cat-options" placeholder=", add…" /></td>
              <td data-col="short_description" class="wcse-wysiwyg" data-wysiwyg-key="short_description"><div class="wcse-wysiwyg-preview">${esc(truncateText(stripTags(String(r.short_description || '')), 120))}</div><button type="button" class="button wcse-wysiwyg-edit">Edit</button></td>
              <td data-col="description" class="wcse-wysiwyg" data-wysiwyg-key="description"><div class="wcse-wysiwyg-preview">${esc(truncateText(stripTags(String(r.description || '')), 120))}</div><button type="button" class="button wcse-wysiwyg-edit">Edit</button></td>
              ${acfCellsHtml(r)}
              <td data-col="type">${esc(r.type)}</td>
            </tr>`
            )
            .join("")}
          </tbody>
        </table>
      </div>
      ${datalistHtml()}`;
    wire();
    initDataTable();
    applyColumnVisibility();
  }

  function esc(s) {
    return (s == null ? "" : String(s)).replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        }[m])
    );
  }

  function tokensFromString(s) {
    return (s || "")
      .split(",")
      .map((x) => x.trim())
      .filter(Boolean);
  }

  function tokensHtml(s) {
    return tokensFromString(s)
      .map(
        (name) =>
          `<span class="wcse-token" data-name="${esc(name)}">${esc(name)}<button type="button" class="wcse-token-x" aria-label="Remove">×</button></span>`
      )
      .join("");
  }

  function label(v) {
    const map = {
      instock: "In stock",
      outofstock: "Out of stock",
      onbackorder: "On backorder",
      publish: "Publish",
      draft: "Draft",
      pending: "Pending",
      private: "Private",
    };
    return map[v] || v;
  }

  function selectHtml(key, values, current) {
    const cur = (current == null ? "" : String(current));
    const opts = values
      .map((v) => {
        if (typeof v === "object" && v && "value" in v) {
          const val = String(v.value);
          const lbl = String(v.label ?? v.value);
          return `<option value="${esc(val)}" ${val === cur ? "selected" : ""}>${esc(lbl)}</option>`;
        }
        const val = String(v);
        return `<option value="${esc(val)}" ${val === cur ? "selected" : ""}>${esc(label(val))}</option>`;
      })
      .join("");
    return `<select class="wcse-select" data-key="${key}">${opts}</select>`;
  }

  function headerHtml() {
    const cols = [
      { key: 'id', label: 'ID', always: true },
      { key: 'name', label: 'Name' },
      { key: 'sku', label: 'SKU' },
      { key: 'regular_price', label: 'Regular' },
      { key: 'sale_price', label: 'Sale' },
      { key: 'stock_status', label: 'Stock' },
      { key: 'stock_qty', label: 'Qty' },
      { key: 'status', label: 'Status' },
      { key: 'categories', label: 'Categories' },
      { key: 'short_description', label: 'Short Description' },
      { key: 'description', label: 'Description' },
    ];
    const parts = [];
    for (const c of cols) {
      if (c.always || isVisible(c.key)) parts.push(`<th data-col="${c.key}">${esc(c.label)}</th>`);
      else parts.push(`<th data-col="${c.key}">${esc(c.label)}</th>`); // added hidden via applyColumnVisibility
    }
    for (const f of (state.acfFields || [])) {
      const key = `acf:${f.key}`;
      parts.push(`<th data-col="${key}">${esc(f.label || f.name)}</th>`);
    }
    parts.push(`<th data-col="type">Type</th>`);
    return parts.join('');
  }

  function isVisible(key) {
    if (!state.visible || state.visible.size === 0) return true;
    if (state.visible.has(key)) return true;
    if (key && key.startsWith('acf:')) {
      const hasAnyAcf = Array.from(state.visible).some((k) => String(k).startsWith('acf:'));
      if (!hasAnyAcf) return true;
      // Back-compat: if saved visibility used acf:name, honor it
      const k = key.slice(4);
      const f = (state.acfFields || []).find((x) => x.key === k);
      if (f && state.visible.has(`acf:${f.name}`)) return true;
    }
    return false;
  }

  function applyColumnVisibility() {
    if (dt && typeof dt.columns === 'function') {
      dt.columns().every(function () {
        const th = this.header();
        const key = th && th.getAttribute('data-col') ? th.getAttribute('data-col') : '';
        const always = key === 'id';
        const visible = always || isVisible(key);
        this.visible(visible, false);
      });
      if (dt.columns && dt.columns.adjust) dt.columns.adjust();
      if (dt.responsive && dt.responsive.recalc) dt.responsive.recalc();
      return;
    }
    // Fallback if DataTables not present
    const table = document.getElementById('wcse-table');
    if (!table) return;
    table.querySelectorAll('[data-col]').forEach((el) => {
      const key = el.getAttribute('data-col');
      if (key === 'id') { el.classList.remove('wcse-col-hidden'); return; }
      if (isVisible(key)) el.classList.remove('wcse-col-hidden');
      else el.classList.add('wcse-col-hidden');
    });
  }

  function columnsPanelHtml() {
    const checked = (key) => (isVisible(key) ? 'checked' : '');
    const base = [
      { key: 'name', label: 'Name' },
      { key: 'sku', label: 'SKU' },
      { key: 'regular_price', label: 'Regular' },
      { key: 'sale_price', label: 'Sale' },
      { key: 'stock_status', label: 'Stock' },
      { key: 'stock_qty', label: 'Qty' },
      { key: 'status', label: 'Status' },
      { key: 'categories', label: 'Categories' },
      { key: 'short_description', label: 'Short Description' },
      { key: 'description', label: 'Description' },
      { key: 'type', label: 'Type' },
    ];
    const acf = (state.acfFields || []).map((f) => ({ key: `acf:${f.key}`, label: f.label || f.name }));
    const items = base.concat(acf);
    const boxes = items.map((c) => `<label><input type="checkbox" class="wcse-col" value="${esc(c.key)}" ${checked(c.key)}> ${esc(c.label)}</label>`).join('');
    return `<div id="columns-panel" class="wcse-columns-panel" hidden>
      <div class="wcse-columns-grid">${boxes}</div>
      <div class="wcse-columns-actions">
        <button id="columns-apply">${WCSE.i18n.apply || 'Apply'}</button>
        <button id="columns-cancel" type="button">${WCSE.i18n.cancel || 'Cancel'}</button>
      </div>
    </div>`;
  }

  function acfCellsHtml(row) {
    const fields = state.acfFields || [];
    const values = row.acf || {};
    return fields
      .map((f) => acfCellHtml(f, values[f.key]))
      .join("");
  }

  function acfTokensHtml(values, f) {
    const tokens = (values || []).map((val) => {
      const label = (f.choices && f.choices[val] != null) ? String(f.choices[val]) : String(val);
      return `<span class="wcse-token" data-name="${esc(val)}">${esc(label)}<button type="button" class="wcse-token-x" aria-label="Remove">×</button></span>`;
    }).join('');
    return tokens;
  }

  function acfCellHtml(f, value) {
    // Multiple values (checkbox or select[multiple]) → token UI
    if (f.type === 'checkbox' || (f.type === 'select' && f.multiple)) {
      const vals = Array.isArray(value) ? value.map(String) : [];
      const listId = `wcse-acf-opts-${f.key}`;
      const tokens = acfTokensHtml(vals, f);
      return `<td data-col="acf:${f.key}" class="wcse-acf-multi" data-acf-multi="${esc(f.key)}">${tokens}<input type="text" class="wcse-token-input" list="${listId}" placeholder=", add…" /></td>`;
    }
    const v = value == null ? "" : String(value);
    if (f.type === "select" || f.type === "radio") {
      const opts = Object.keys(f.choices || {}).map((key) => ({ value: key, label: f.choices[key] }));
      return `<td data-col="acf:${f.key}">${selectHtml(`acf:${f.key}`, opts, v)}</td>`;
    }
    if (f.type === "true_false") {
      return `<td data-col="acf:${f.key}">${selectHtml(`acf:${f.key}`, [
        { value: "0", label: "No" },
        { value: "1", label: "Yes" },
      ], (v === "1" || v === "true" || v === "yes") ? "1" : String(v))}</td>`;
    }
    if (f.type === 'wysiwyg') {
      const preview = truncateText(stripTags(String(v || '')), 120);
      return `<td data-col="acf:${f.key}" class="wcse-wysiwyg" data-acf-wysiwyg="${esc(f.key)}"><div class="wcse-wysiwyg-preview">${esc(preview)}</div><button type="button" class="button wcse-wysiwyg-edit">Edit</button></td>`;
    }
    // text / number
    const extra = f.type === "number" ? ' data-acf-type="number"' : '';
    return `<td data-col="acf:${f.key}" contenteditable data-acf-key="${esc(f.key)}"${extra}>${esc(v)}</td>`;
  }

  function stripTags(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  }

  function truncateText(s, max) {
    if (s.length <= max) return s;
    return s.slice(0, max - 1) + '…';
  }

  function datalistHtml() {
    const parts = [];
    if (state.cats && state.cats.length) {
      const opts = state.cats.map((n) => `<option value="${esc(n)}"></option>`).join("");
      parts.push(`<datalist id="wcse-cat-options">${opts}</datalist>`);
    }
    // ACF multiple choices datalists
    (state.acfFields || []).forEach((f) => {
      if (f.type === 'checkbox' || (f.type === 'select' && f.multiple)) {
        const id = `wcse-acf-opts-${f.key}`;
        const opts = Object.keys(f.choices || {}).map((key) => `<option value="${esc(key)}">${esc(String(f.choices[key]))}</option>`).join("");
        parts.push(`<datalist id="${id}">${opts}</datalist>`);
      }
    });
    return parts.join('');
  }

  function wire() {
    el.querySelector("#prev")?.addEventListener("click", () =>
      load(state.page - 1)
    );
    el.querySelector("#next")?.addEventListener("click", () =>
      load(state.page + 1)
    );
    el.querySelector("#save")?.addEventListener("click", save);
    el.querySelector("#columns")?.addEventListener("click", () => {
      const panel = document.getElementById('columns-panel');
      if (panel) panel.hidden = !panel.hidden;
    });
    el.querySelector("#columns-cancel")?.addEventListener("click", () => {
      const panel = document.getElementById('columns-panel');
      if (panel) panel.hidden = true;
    });
    el.querySelector("#columns-apply")?.addEventListener("click", async () => {
      const selected = Array.from(document.querySelectorAll('#columns-panel .wcse-col:checked')).map((i) => i.value);
      try {
        await api('wcse/v1/settings', { method: 'POST', data: { visible_fields: selected } });
        state.visible = new Set(selected);
        render();
      } catch (e) {
        alert('Failed to save settings');
      }
    });
    // Live apply when toggling checkboxes; also persist (debounced)
    bindColumnsPanelLive();
    // Delegate contenteditable inputs
    el.querySelector("tbody")?.addEventListener("input", (e) => {
      const cell = e.target.closest && e.target.closest("td[contenteditable]");
      if (!cell) return;
      const tr = cell.closest("tr");
      const id = Number(tr?.dataset?.id || 0);
      if (!id) return;
      const key = cell.getAttribute("data-key") || "";
      const draft = {
        ...state.rows.find((r) => r.ID === id),
        ...(state.dirty.get(id) || {}),
      };
      let val = cell.innerText.trim();
      if (key === "stock_qty") {
        if (val === "") {
          draft[key] = null;
        } else {
          const cleaned = val.replace(/[^\d]/g, "");
          if (cleaned !== val) cell.innerText = cleaned;
          draft[key] = cleaned;
        }
      } else if (key === "regular_price" || key === "sale_price") {
        if (val === "") {
          draft[key] = "";
        } else {
          let cleaned = val.replace(/[^\d.]/g, "");
          const firstDot = cleaned.indexOf(".");
          if (firstDot !== -1) {
            cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, "");
          }
          if (cleaned !== val) cell.innerText = cleaned;
          draft[key] = cleaned;
        }
      } else if (cell.hasAttribute("data-acf-key")) {
        const fieldKey = cell.getAttribute("data-acf-key");
        const type = cell.getAttribute("data-acf-type") || "text";
        if (type === "number" && val !== "") {
          let cleaned = val.replace(/[^\d.]/g, "");
          const firstDot = cleaned.indexOf(".");
          if (firstDot !== -1) cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, "");
          if (cleaned !== val) cell.innerText = cleaned;
          val = cleaned;
        }
        draft.acf = { ...(draft.acf || {}) };
        draft.acf[fieldKey] = val;
      } else if (key) {
        draft[key] = val;
      }
      state.dirty.set(id, draft);
      tr.classList.add("wcse-dirty");
      el.querySelector("#save").disabled = false;
      el.querySelector("#save").textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
    });

    // Delegate select changes (core + ACF)
    el.querySelector("tbody")?.addEventListener("change", (e) => {
      const select = e.target.closest && e.target.closest("select.wcse-select");
      if (!select) return;
      const tr = select.closest("tr");
      const id = Number(tr?.dataset?.id || 0);
      if (!id) return;
      const key = select.getAttribute("data-key");
      const draft = {
        ...state.rows.find((r) => r.ID === id),
        ...(state.dirty.get(id) || {}),
      };
      if (key && key.startsWith('acf:')) {
        const fieldKey = key.split(':')[1];
        draft.acf = { ...(draft.acf || {}) };
        draft.acf[fieldKey] = select.value;
      } else if (key) {
        draft[key] = select.value;
      }
      state.dirty.set(id, draft);
      tr.classList.add("wcse-dirty");
      el.querySelector("#save").disabled = false;
      el.querySelector("#save").textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
    });

    // ACF events handled by delegated listeners above

    // Tokenized categories field
    el.querySelectorAll("td.wcse-categories").forEach((td) => {
      const input = td.querySelector("input.wcse-token-input");
      const tr = td.closest("tr");
      const id = Number(tr.dataset.id);

      function commit() {
        const names = Array.from(td.querySelectorAll(".wcse-token")).map((t) =>
          t.getAttribute("data-name")
        );
        const draft = {
          ...state.rows.find((r) => r.ID === id),
          ...(state.dirty.get(id) || {}),
        };
        draft["categories"] = names.join(", ");
        state.dirty.set(id, draft);
        tr.classList.add("wcse-dirty");
        el.querySelector("#save").disabled = false;
        el.querySelector("#save").textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
      }

      td.addEventListener("click", (e) => {
        if (e.target.classList.contains("wcse-token-x")) {
          e.preventDefault();
          e.target.closest(".wcse-token")?.remove();
          commit();
        }
        input?.focus();
      });

      input?.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === ",") {
          e.preventDefault();
          const val = input.value.trim();
          if (!val) return;
          addToken(td, val);
          input.value = "";
          commit();
        } else if (e.key === "Backspace" && input.value === "") {
          const last = td.querySelector(".wcse-token:last-of-type");
          if (last) {
            last.remove();
            commit();
          }
        }
      });

      input?.addEventListener("change", () => {
        const val = input.value.trim();
        if (!val) return;
        addToken(td, val);
        input.value = "";
        commit();
      });
    });

    // Tokenized ACF multi fields (checkbox, select[multiple])
    el.querySelectorAll('td.wcse-acf-multi').forEach((td) => {
      const input = td.querySelector('input.wcse-token-input');
      const tr = td.closest('tr');
      const id = Number(tr.dataset.id);
      const fieldKey = td.getAttribute('data-acf-multi');
      if (!fieldKey) return;

      function commit() {
        const values = Array.from(td.querySelectorAll('.wcse-token')).map((t) => t.getAttribute('data-name')).filter(Boolean);
        const draft = {
          ...state.rows.find((r) => r.ID === id),
          ...(state.dirty.get(id) || {}),
        };
        draft.acf = { ...(draft.acf || {}) };
        draft.acf[fieldKey] = values;
        state.dirty.set(id, draft);
        tr.classList.add('wcse-dirty');
        el.querySelector('#save').disabled = false;
        el.querySelector('#save').textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
      }

      td.addEventListener('click', (e) => {
        if (e.target.classList.contains('wcse-token-x')) {
          e.preventDefault();
          e.target.closest('.wcse-token')?.remove();
          commit();
        }
        input?.focus();
      });

      input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
          e.preventDefault();
          const val = input.value.trim();
          if (!val) return;
          addToken(td, val);
          input.value = '';
          commit();
        } else if (e.key === 'Backspace' && input.value === '') {
          const last = td.querySelector('.wcse-token:last-of-type');
          if (last) {
            last.remove();
            commit();
          }
        }
      });

      input?.addEventListener('change', () => {
        const val = input.value.trim();
        if (!val) return;
        addToken(td, val);
        input.value = '';
        commit();
      });
    });

    // WYSIWYG edit button
    el.querySelector('tbody')?.addEventListener('click', (e) => {
      const btn = e.target.closest('.wcse-wysiwyg-edit');
      if (!btn) return;
      const td = btn.closest('td.wcse-wysiwyg');
      const tr = btn.closest('tr');
      const id = Number(tr?.dataset?.id || 0);
      const fieldKey = td?.getAttribute('data-acf-wysiwyg') || td?.getAttribute('data-wysiwyg-key');
      if (!id || !fieldKey) return;
      const row = state.rows.find((r) => r.ID === id) || {};
      const isAcf = /^field_/.test(fieldKey);
      const current = isAcf ? ((state.dirty.get(id)?.acf?.[fieldKey]) ?? (row.acf?.[fieldKey] ?? '')) : ((state.dirty.get(id)?.[fieldKey]) ?? (row[fieldKey] ?? ''));
      openWysiwygModal(id, fieldKey, String(current));
    });
  }

  let saveVisibleTimer = null;
  function bindColumnsPanelLive() {
    const panel = document.getElementById('columns-panel');
    if (!panel) return;
    panel.querySelectorAll('.wcse-col').forEach((cb) => {
      cb.addEventListener('change', () => {
        const selected = Array.from(panel.querySelectorAll('.wcse-col:checked')).map((i) => i.value);
        state.visible = new Set(selected);
        applyColumnVisibility();
        // Debounce save to server
        if (saveVisibleTimer) clearTimeout(saveVisibleTimer);
        saveVisibleTimer = setTimeout(async () => {
          try { await api('wcse/v1/settings', { method: 'POST', data: { visible_fields: selected } }); } catch (e) {}
        }, 500);
      });
    });
  }

  function ensureModal() {
    let m = document.getElementById('wcse-modal');
    if (m) return m;
    const html = `
      <div id="wcse-modal" class="wcse-modal" hidden>
        <div class="wcse-modal-backdrop"></div>
        <div class="wcse-modal-dialog">
          <div class="wcse-modal-header">
            <h2>Edit Content</h2>
          </div>
          <div class="wcse-modal-body">
            <textarea id="wcse-modal-textarea" rows="14" style="width:100%"></textarea>
          </div>
          <div class="wcse-modal-footer">
            <button id="wcse-modal-save" class="button button-primary">Save</button>
            <button id="wcse-modal-cancel" class="button">Cancel</button>
          </div>
        </div>
      </div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
    return document.getElementById('wcse-modal');
  }

  function openWysiwygModal(id, name, value) {
    const modal = ensureModal();
    const ta = modal.querySelector('#wcse-modal-textarea');
    ta.value = value || '';
    modal.hidden = false;
    // Initialize TinyMCE if available
    let editor;
    if (window.tinymce) {
      // Remove existing instance if any
      const existing = window.tinymce.get('wcse-modal-textarea');
      if (existing) existing.remove();
      window.tinymce.init({
        selector: '#wcse-modal-textarea',
        menubar: false,
        branding: false,
        height: 320,
        convert_urls: false,
        // Use only core plugins available in WordPress bundle to avoid 404s
        plugins: 'lists link paste',
        toolbar: 'formatselect | bold italic underline | bullist numlist | alignleft aligncenter alignright | link | removeformat',
        block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre',
        setup: function (ed) { editor = ed; },
      });
    }
    const onCancel = () => { modal.hidden = true; cleanup(); };
    const onSave = () => {
      // Save raw HTML; server sanitizes via wp_kses_post
      const html = (window.tinymce && window.tinymce.get('wcse-modal-textarea')) ? window.tinymce.get('wcse-modal-textarea').getContent() : ta.value;
      const draft = { ...(state.rows.find((r) => r.ID === id) || {}), ...(state.dirty.get(id) || {}) };
      if (/^field_/.test(name)) {
        draft.acf = { ...(draft.acf || {}) };
        draft.acf[name] = html;
      } else {
        draft[name] = html;
      }
      state.dirty.set(id, draft);
      // Update preview text in cell
      const tr = document.querySelector(`tr[data-id="${id}"]`);
      const cell = tr?.querySelector(`td.wcse-wysiwyg[data-acf-wysiwyg="${CSS.escape(name)}"] .wcse-wysiwyg-preview`);
      if (cell) cell.textContent = truncateText(stripTags(html), 120);
      const rowEl = tr;
      rowEl?.classList.add('wcse-dirty');
      const btn = document.getElementById('save');
      if (btn) {
        btn.disabled = false;
        btn.textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
      }
      modal.hidden = true;
      cleanup();
    };
    const cleanup = () => {
      modal.querySelector('#wcse-modal-save')?.removeEventListener('click', onSave);
      modal.querySelector('#wcse-modal-cancel')?.removeEventListener('click', onCancel);
      modal.querySelector('.wcse-modal-backdrop')?.removeEventListener('click', onCancel);
      if (window.tinymce) {
        const inst = window.tinymce.get('wcse-modal-textarea');
        if (inst) inst.remove();
      }
    };
    modal.querySelector('#wcse-modal-save')?.addEventListener('click', onSave);
    modal.querySelector('#wcse-modal-cancel')?.addEventListener('click', onCancel);
    modal.querySelector('.wcse-modal-backdrop')?.addEventListener('click', onCancel);
    ta.focus();
  }

  function addToken(td, name) {
    const exists = Array.from(td.querySelectorAll(".wcse-token")).some(
      (t) => (t.getAttribute("data-name") || "").toLowerCase() === name.toLowerCase()
    );
    if (exists) return;
    const span = document.createElement("span");
    span.className = "wcse-token";
    span.setAttribute("data-name", name);
    span.innerHTML = `${esc(name)}<button type="button" class="wcse-token-x" aria-label="Remove">×</button>`;
    const input = td.querySelector("input.wcse-token-input");
    td.insertBefore(span, input || null);
  }

  function initDataTable() {
    const $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.dataTable) return;
    const table = $("#wcse-table");
    if (!table.length) return;
    if ($.fn.dataTable.isDataTable(table)) {
      table.DataTable().destroy();
    }
    dt = table.DataTable({
      paging: false, // keep server-side pagination toolbar
      searching: false,
      info: false,
      // Keep all columns in main table to allow editing
      responsive: { details: false },
      scrollX: true,
      autoWidth: false,
      ordering: false,
      columnDefs: [
        { targets: '_all', className: 'all' },
      ],
      fixedColumns: {
        leftColumns: 3,
      },
    });
    applyColumnVisibility();
  }

  async function load(page = 1) {
    await ensureCategories();
    const res = await api(
      `wcse/v1/products?page=${page}&per_page=${WCSE.perPage}`
    );
    state.page = page;
    state.pages = res.pages || 1;
    state.rows = res.items || [];
    state.acfFields = res.acf_fields || [];
    state.dirty.clear();
    render();
  }

  async function ensureCategories() {
    if (state.cats && state.cats.length) return;
    const names = [];
    let p = 1;
    while (p < 50) {
      const list = await api(
        `wp/v2/product_cat?per_page=100&page=${p}&_fields=name&orderby=name&order=asc`
      );
      if (!Array.isArray(list) || list.length === 0) break;
      for (const item of list) if (item && item.name) names.push(item.name);
      if (list.length < 100) break;
      p += 1;
    }
    state.cats = Array.from(new Set(names)).sort((a, b) => a.localeCompare(b));
  }

  async function save() {
    const btn = el.querySelector("#save");
    btn.disabled = true;
    btn.textContent = WCSE.i18n.saving;
    const payload = Array.from(state.dirty.values()).map((row) => {
      ["regular_price", "sale_price", "stock_qty"].forEach((k) => {
        const v = row[k];
        if (v === null || v === undefined || v === "") return;
        const n = Number(v);
        if (Number.isNaN(n)) {
          delete row[k];
          return;
        }
        if (k === "stock_qty") row[k] = Math.round(n);
        else row[k] = String(n);
      });
      return row;
    });
    try {
      const result = await api("wcse/v1/products", {
        method: "POST",
        data: payload,
      });
      const errors = result.filter((r) => !r.ok);
      if (errors.length)
        alert(errors.map((e) => `#${e.ID}: ${e.error}`).join("\n"));
      await load(state.page);
    } finally {
      btn.textContent = `${WCSE.i18n.save} (${state.dirty.size})`;
    }
  }

  load(1);
})();
