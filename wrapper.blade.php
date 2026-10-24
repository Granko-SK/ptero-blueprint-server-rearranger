<style>
  .so-handle { display: inline-block; vertical-align: middle; margin-right: 10px; padding: 4px 2px; color: #7d8b99; cursor: grab; touch-action: none; line-height: 0; user-select: none; -webkit-user-select: none; -webkit-user-drag: none; }
  .so-handle:hover { color: #ffffff; }
  .so-handle svg { display: block; width: 10px; height: 16px; fill: currentColor; pointer-events: none; }
  tbody tr > td { transition: box-shadow 160ms ease; }
  tr.so-lifted { position: relative; z-index: 10; }
  tr.so-lifted > td { box-shadow: inset 0 0 0 9999px rgba(0, 166, 90, 0.14), 0 12px 28px rgba(0, 0, 0, 0.45); }
  body.so-busy { cursor: grabbing; user-select: none; -webkit-user-select: none; }
  body.so-busy tbody tr { transition: transform 200ms cubic-bezier(0.2, 0.7, 0.2, 1); }
  @media (prefers-reduced-motion: reduce) {
    tbody tr > td, body.so-busy tbody tr { transition: none !important; }
  }
  #so-toast { position: fixed; right: 20px; bottom: 20px; z-index: 9999; padding: 8px 14px; border-radius: 4px; color: #ffffff; font-size: 13px; background: #00a65a; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
  #so-toast.so-show { opacity: 1; }
  #so-toast.so-error { background: #dd4b39; }
</style>
<script>
(function () {
  // Only the Servers list page. Everything else in the admin panel is left alone.
  var path = window.location.pathname.replace(/\/+$/, '');
  if (path !== '/admin/servers') return;

  var ENDPOINT = '/admin/extensions/serverorder';
  var UUID = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i;
  var LIFT = 6; // how far the dragged row rises above the pointer, in px
  var meta = document.querySelector('meta[name="_token"]');
  var csrf = meta ? meta.getAttribute('content') : '';
  var toastTimer = null;
  var lastSaved = null;
  var dragEndedAt = 0;

  function rowUuid(tr) {
    var match = tr.textContent.match(UUID);
    return match ? match[0].toLowerCase() : null;
  }

  // A server row links to /admin/servers/view/{id} and contains the server's UUID.
  function isServerRow(el) {
    return !!(el && el.tagName === 'TR' && el.querySelector('a[href*="/admin/servers/view/"]') && rowUuid(el));
  }

  function serverRows() {
    return Array.prototype.filter.call(document.querySelectorAll('tbody tr'), isServerRow);
  }

  function siblingRows(tr) {
    return Array.prototype.filter.call(tr.parentNode.children, isServerRow);
  }

  function prevRow(el) {
    var n = el.previousElementSibling;
    while (n && !isServerRow(n)) n = n.previousElementSibling;
    return n;
  }

  function nextRow(el) {
    var n = el.nextElementSibling;
    while (n && !isServerRow(n)) n = n.nextElementSibling;
    return n;
  }

  function midpoint(el) {
    var r = el.getBoundingClientRect();
    return r.top + r.height / 2;
  }

  function visibleOrder(rows) {
    return rows.map(rowUuid);
  }

  function toast(message, isError) {
    var el = document.getElementById('so-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'so-toast';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.className = isError ? 'so-error so-show' : 'so-show';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      el.className = isError ? 'so-error' : '';
    }, 1800);
  }

  function save(rows) {
    var visible = visibleOrder(rows);
    var key = visible.join(',');
    if (key === lastSaved) return;
    lastSaved = key;

    fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({ visible: visible })
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      toast('Server order saved');
    }).catch(function () {
      lastSaved = null;
      toast('Could not save the order', true);
    });
  }

  var rows = serverRows();
  if (!rows.length) return;
  var tbody = rows[0].parentNode;

  function sortRows(order) {
    var rank = function (tr) {
      var index = order.indexOf(rowUuid(tr));
      return index === -1 ? Infinity : index;
    };
    var sorted = rows.slice().sort(function (a, b) {
      var ra = rank(a);
      var rb = rank(b);
      return ra === rb ? 0 : (ra < rb ? -1 : 1);
    });
    sorted.forEach(function (tr) {
      tbody.appendChild(tr);
    });
  }

  // A drag ends with a click on whatever was under the pointer. Swallow it so the
  // row's own click handling (or a link) doesn't fire.
  window.addEventListener('click', function (e) {
    if (Date.now() - dragEndedAt < 500) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  function blockNativeDrag(e) {
    e.preventDefault();
  }

  // Drag by the handle. Works with mouse, pen and touch.
  //
  // The dragged row lifts a little and follows the pointer. When it crosses the middle
  // of a neighbour, the two swap places in the table, and every other row slides from
  // where it was to its new spot (FLIP: measure, move, then animate back to zero).
  //
  // Moves and the release are listened for on window, not on the handle. Moving the row
  // also moves the handle, which drops its pointer capture, so a handle-only listener
  // would never see the release and the row would stay stuck.
  function attachDrag(handle, tr) {
    var activePointer = null;
    var grabOffset = 0; // pointer distance from the row's top at pointerdown
    var height = 0;
    var targetTop = 0;  // where the lifted row's top should be on screen
    var dragY = 0;      // translateY currently applied to the lifted row

    // Put the lifted row at targetTop on screen, whatever its layout position is.
    function placeDragged() {
      var layoutTop = tr.getBoundingClientRect().top - dragY;
      dragY = targetTop - layoutTop;
      tr.style.transform = 'translateY(' + dragY + 'px)';
    }

    // Run a DOM change that moves the dragged row, and animate the other rows into place.
    function moveWith(mutate) {
      var others = siblingRows(tr).filter(function (el) { return el !== tr; });
      var before = others.map(function (el) { return el.getBoundingClientRect().top; });

      mutate();

      // Clear transforms so the layout positions can be measured.
      others.forEach(function (el) {
        el.style.transition = 'none';
        el.style.transform = '';
      });
      tr.style.transform = '';
      var after = others.map(function (el) { return el.getBoundingClientRect().top; });
      var layoutTop = tr.getBoundingClientRect().top;

      // Put each row back where it was on screen...
      others.forEach(function (el, i) {
        var delta = before[i] - after[i];
        if (Math.abs(delta) > 0.5) el.style.transform = 'translateY(' + delta + 'px)';
      });
      dragY = targetTop - layoutTop;
      tr.style.transform = 'translateY(' + dragY + 'px)';

      // ...apply those offsets before the transition starts...
      void tr.parentNode.offsetHeight;

      // ...then let them slide to their new layout positions.
      others.forEach(function (el) {
        el.style.transition = '';
        el.style.transform = '';
      });
    }

    function reorder(centerY) {
      for (var guard = 0; guard < 100; guard++) {
        var next = nextRow(tr);
        var prev = prevRow(tr);
        if (next && centerY > midpoint(next)) {
          moveWith(function () { tr.parentNode.insertBefore(tr, next.nextSibling); });
        } else if (prev && centerY < midpoint(prev)) {
          moveWith(function () { tr.parentNode.insertBefore(tr, prev); });
        } else {
          break;
        }
      }
    }

    function endDrag() {
      if (activePointer === null) return;
      activePointer = null;
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onEnd);
      window.removeEventListener('pointercancel', onEnd);
      window.removeEventListener('dragstart', blockNativeDrag, true);

      tr.classList.remove('so-lifted');
      document.body.classList.remove('so-busy');

      // Settle the row into its slot.
      tr.style.transition = 'transform 180ms cubic-bezier(0.2, 0.7, 0.2, 1)';
      tr.style.transform = '';
      dragY = 0;

      dragEndedAt = Date.now();
      save(serverRows());
    }

    function onMove(e) {
      if (e.pointerId !== activePointer) return;

      // Safety net: the button was released somewhere we didn't see.
      if (e.pointerType === 'mouse' && e.buttons === 0) {
        endDrag();
        return;
      }

      targetTop = e.clientY - grabOffset - LIFT;
      reorder(targetTop + height / 2);
      placeDragged();
    }

    function onEnd(e) {
      if (e.pointerId !== activePointer) return;
      endDrag();
    }

    handle.setAttribute('draggable', 'false');
    handle.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      if (activePointer !== null) return;

      // Keep the row's own handlers (and text selection) out of the drag.
      e.preventDefault();
      e.stopPropagation();

      var rect = tr.getBoundingClientRect();
      grabOffset = e.clientY - rect.top;
      height = rect.height;
      dragY = 0;
      targetTop = rect.top - LIFT;
      activePointer = e.pointerId;

      tr.style.transition = 'none';
      tr.classList.add('so-lifted');
      document.body.classList.add('so-busy');
      placeDragged();

      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onEnd);
      window.addEventListener('pointercancel', onEnd);
      window.addEventListener('dragstart', blockNativeDrag, true);
    });
  }

  rows.forEach(function (tr) {
    var cell = tr.lastElementChild;
    if (!cell || cell.querySelector('.so-handle')) return;

    var handle = document.createElement('span');
    handle.className = 'so-handle';
    handle.title = 'Drag to reorder';
    handle.innerHTML = '<svg viewBox="0 0 10 16" aria-hidden="true" focusable="false">' +
      '<circle cx="2.5" cy="2.5" r="1.5"/><circle cx="7.5" cy="2.5" r="1.5"/>' +
      '<circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/>' +
      '<circle cx="2.5" cy="13.5" r="1.5"/><circle cx="7.5" cy="13.5" r="1.5"/>' +
      '</svg>';
    cell.insertBefore(handle, cell.firstChild);
    attachDrag(handle, tr);
  });

  // Show the saved order when the page loads.
  fetch(ENDPOINT + '?format=json', {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  }).then(function (res) {
    return res.ok ? res.json() : { order: [] };
  }).then(function (data) {
    sortRows(Array.isArray(data.order) ? data.order : []);
    lastSaved = visibleOrder(serverRows()).join(',');
  }).catch(function () {});
})();
</script>
