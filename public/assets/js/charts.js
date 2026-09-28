/* ==========================================================================
   EcoCycle — charts.js
   Dependency-free SVG charts. Colours are CSS custom properties, so every
   chart follows the theme automatically with no redraw.

   Declarative:
     <svg data-chart="area" data-values="10,12,9,14" data-labels="M,T,W,T"></svg>
   Imperative:
     EC.charts.area(node, [10,12,9], { labels: [...], color: 'var(--c2)' })
   ========================================================================== */
(function (global) {
  'use strict';
  var EC = global.EC = global.EC || {};

  function ns(tag, attrs) {
    var n = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (var k in attrs) if (attrs.hasOwnProperty(k)) n.setAttribute(k, attrs[k]);
    return n;
  }
  function clear(svg) { while (svg.firstChild) svg.removeChild(svg.firstChild); }
  function extent(vals, padTop) {
    var min = Math.min.apply(null, vals), max = Math.max.apply(null, vals);
    if (min === max) { max = min + 1; }
    var span = max - min;
    return { min: Math.max(0, min - span * 0.22), max: max + span * (padTop || 0.14) };
  }
  function uid(p) { uid.n = (uid.n || 0) + 1; return p + uid.n; }
  function allZero(vals) { return vals.every(function (v) { return !v; }); }

  /* Rounding every tick to a whole number repeats labels once the range is
     small ("1, 1, 1, 0, 0"), so the decimals follow the step between ticks. */
  function tickText(val, step) {
    var d = step >= 1 ? 0 : step >= 0.1 ? 1 : 2;
    return val.toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  /* A fixed 640-unit viewBox on a width:100% <svg> means the whole drawing —
     bars, type, stroke weights — is scaled by however wide the container
     happens to be, so the same chart is squat in a sidebar and enormous in a
     full-width card. Measuring the element and drawing 1:1 into that many user
     units keeps every chart at its intended height and its labels at their
     intended size. */
  function frame(svg, h) {
    var w = Math.round((svg.getBoundingClientRect && svg.getBoundingClientRect().width) || 0);
    if (!w || w < 240) w = 640;                 /* hidden tab panel, or no layout yet */
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svg.style.height = h + 'px';
    return w;
  }

  /* --- Sparkline: bare trend line, no axes ------------------------------- */
  function sparkline(svg, vals, opts) {
    opts = opts || {};
    var w = 100, h = 30, pad = 2;
    var e = extent(vals, 0.1);
    var color = opts.color || 'var(--primary)';
    clear(svg);
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    svg.setAttribute('preserveAspectRatio', 'none');

    var pts = vals.map(function (v, i) {
      var x = pad + (i / (vals.length - 1)) * (w - pad * 2);
      var y = h - pad - ((v - e.min) / (e.max - e.min)) * (h - pad * 2);
      return [x, y];
    });
    var d = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2); }).join(' ');

    if (opts.fill !== false) {
      var gid = uid('sg');
      var grad = ns('linearGradient', { id: gid, x1: '0', y1: '0', x2: '0', y2: '1' });
      grad.appendChild(ns('stop', { offset: '0', 'stop-color': color, 'stop-opacity': '.26' }));
      grad.appendChild(ns('stop', { offset: '1', 'stop-color': color, 'stop-opacity': '0' }));
      var defs = ns('defs'); defs.appendChild(grad); svg.appendChild(defs);
      svg.appendChild(ns('path', {
        d: d + ' L' + pts[pts.length - 1][0].toFixed(2) + ' ' + h + ' L' + pts[0][0].toFixed(2) + ' ' + h + ' Z',
        fill: 'url(#' + gid + ')'
      }));
    }
    svg.appendChild(ns('path', {
      d: d, fill: 'none', stroke: color, 'stroke-width': opts.weight || 1.8,
      'stroke-linecap': 'round', 'stroke-linejoin': 'round',
      'vector-effect': 'non-scaling-stroke'
    }));
    if (opts.dot !== false) {
      var last = pts[pts.length - 1];
      svg.appendChild(ns('circle', { cx: last[0], cy: last[1], r: 2.2, fill: color,
        stroke: 'var(--surface)', 'stroke-width': 1.4, 'vector-effect': 'non-scaling-stroke' }));
    }
  }

  /* --- Area / line chart with axes --------------------------------------- */
  function area(svg, vals, opts) {
    opts = opts || {};
    var h = opts.height || 220;
    var L = 44, R = 10, T = 14, B = 26;
    var color = opts.color || 'var(--primary)';
    var e = extent(vals, 0.16);
    clear(svg);
    svg.classList.add('chart');
    var w = frame(svg, h);

    var iw = w - L - R, ih = h - T - B;
    var ticks = 4;
    var step = (e.max - e.min) / ticks;
    /* Nothing in either series: keep the baseline, drop the scale above it --
       a scale drawn for no data reads as data. */
    var empty = allZero(vals) && (!opts.compare || allZero(opts.compare));
    for (var t = 0; t <= ticks; t++) {
      var y = T + (t / ticks) * ih;
      svg.appendChild(ns('line', { x1: L, y1: y, x2: w - R, y2: y, class: 'grid-line' }));
      var val = e.max - (t / ticks) * (e.max - e.min);
      var lbl = ns('text', { x: L - 8, y: y + 3.5, 'text-anchor': 'end' });
      lbl.textContent = opts.fmt ? opts.fmt(val) : empty ? (t === ticks ? '0' : '') : tickText(val, step);
      svg.appendChild(lbl);
    }

    var pts = vals.map(function (v, i) {
      return [L + (i / (vals.length - 1)) * iw, T + ih - ((v - e.min) / (e.max - e.min)) * ih];
    });
    var d = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');

    var gid = uid('ag');
    var defs = ns('defs');
    var grad = ns('linearGradient', { id: gid, x1: '0', y1: '0', x2: '0', y2: '1' });
    grad.appendChild(ns('stop', { offset: '0', 'stop-color': color, 'stop-opacity': '.24' }));
    grad.appendChild(ns('stop', { offset: '1', 'stop-color': color, 'stop-opacity': '0' }));
    defs.appendChild(grad); svg.appendChild(defs);

    svg.appendChild(ns('path', { d: d + ' L' + pts[pts.length - 1][0] + ' ' + (T + ih) + ' L' + pts[0][0] + ' ' + (T + ih) + ' Z', fill: 'url(#' + gid + ')' }));

    /* Optional comparison series drawn as a dashed line. */
    if (opts.compare) {
      var cp = opts.compare.map(function (v, i) {
        return [L + (i / (opts.compare.length - 1)) * iw, T + ih - ((v - e.min) / (e.max - e.min)) * ih];
      });
      svg.appendChild(ns('path', {
        d: cp.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' '),
        fill: 'none', stroke: opts.compareColor || 'var(--c2)', 'stroke-width': 1.6, 'stroke-dasharray': '4 4', opacity: '.85'
      }));
    }

    svg.appendChild(ns('path', { d: d, fill: 'none', stroke: color, 'stroke-width': 2.2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));

    pts.forEach(function (p, i) {
      if (opts.dots === false && i !== pts.length - 1) return;
      svg.appendChild(ns('circle', { cx: p[0], cy: p[1], r: i === pts.length - 1 ? 4 : 2.6,
        fill: i === pts.length - 1 ? color : 'var(--surface)', stroke: color, 'stroke-width': 1.8 }));
    });

    if (opts.labels) {
      var every = Math.ceil(opts.labels.length / 8);
      opts.labels.forEach(function (s, i) {
        if (i % every !== 0 && i !== opts.labels.length - 1) return;
        var tx = ns('text', { x: L + (i / (vals.length - 1)) * iw, y: h - 7, 'text-anchor': 'middle' });
        tx.textContent = s;
        svg.appendChild(tx);
      });
    }
  }

  /* --- Bars (vertical or horizontal) -------------------------------------- */
  function bars(svg, vals, opts) {
    opts = opts || {};
    var h = opts.height || 200;
    var L = opts.labelWidth || 40, R = 10, T = 10, B = 24;
    clear(svg);
    svg.classList.add('chart');
    var w = frame(svg, h);
    var iw = w - L - R, ih = h - T - B;
    /* An empty week still draws its baseline and stubs; dividing by a zero
       peak would give every bar a NaN height instead. */
    var empty = allZero(vals);
    var max = (empty ? 1 : Math.max.apply(null, vals)) * 1.12;
    var gap = opts.gap == null ? 6 : opts.gap;
    /* Keep bars readable rather than letting six values become six slabs. */
    var bw = Math.min((iw / vals.length) - gap, opts.maxBarWidth || 64);

    for (var t = 0; t <= 3; t++) {
      var y = T + (t / 3) * ih;
      svg.appendChild(ns('line', { x1: L, y1: y, x2: w - R, y2: y, class: 'grid-line' }));
      var lb = ns('text', { x: L - 8, y: y + 3.5, 'text-anchor': 'end' });
      var tv = max - (t / 3) * max;
      lb.textContent = opts.fmt ? opts.fmt(tv) : empty ? (t === 3 ? '0' : '') : tickText(tv, max / 3);
      svg.appendChild(lb);
    }

    /* With a capped bar width the group is narrower than the plot area, so
       centre it rather than letting it hug the axis. */
    var span = vals.length * (bw + gap);
    var x0 = L + Math.max(0, (iw - span) / 2);

    vals.forEach(function (v, i) {
      var bh = Math.max(2, (v / max) * ih);
      var x = x0 + i * (bw + gap) + gap / 2;
      var col = opts.colors ? opts.colors[i % opts.colors.length] : (opts.color || 'var(--primary)');
      var r = ns('rect', { x: x, y: T + ih - bh, width: bw, height: bh, rx: Math.min(4, bw / 2), fill: col });
      if (opts.highlight != null && i === opts.highlight) r.setAttribute('opacity', '1');
      else if (opts.highlight != null) r.setAttribute('opacity', '.38');
      svg.appendChild(r);
      if (opts.labels && opts.labels[i]) {
        var tx = ns('text', { x: x + bw / 2, y: h - 7, 'text-anchor': 'middle' });
        tx.textContent = opts.labels[i];
        svg.appendChild(tx);
      }
    });
  }

  /* --- Donut --------------------------------------------------------------- */
  function donut(svg, items, opts) {
    opts = opts || {};
    var size = 180, cx = size / 2, cy = size / 2;
    var r = opts.r || 66, thick = opts.thick || 22;
    clear(svg);
    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
    var total = items.reduce(function (s, it) { return s + it.v; }, 0) || 1;
    var angle = -Math.PI / 2;
    var gapRad = 0.022;

    items.forEach(function (it) {
      var sweep = (it.v / total) * Math.PI * 2;
      var a0 = angle + gapRad / 2, a1 = angle + sweep - gapRad / 2;
      if (a1 <= a0) { angle += sweep; return; }
      var large = (a1 - a0) > Math.PI ? 1 : 0;
      var d = 'M' + (cx + r * Math.cos(a0)) + ' ' + (cy + r * Math.sin(a0)) +
              ' A' + r + ' ' + r + ' 0 ' + large + ' 1 ' + (cx + r * Math.cos(a1)) + ' ' + (cy + r * Math.sin(a1));
      svg.appendChild(ns('path', { d: d, fill: 'none', stroke: it.color, 'stroke-width': thick, 'stroke-linecap': 'butt' }));
      angle += sweep;
    });

    if (opts.center) {
      var v = ns('text', { x: cx, y: cy - 1, 'text-anchor': 'middle', style: 'font-size:24px;font-weight:660;fill:var(--text)' });
      v.textContent = opts.center;
      svg.appendChild(v);
      if (opts.centerSub) {
        var s = ns('text', { x: cx, y: cy + 17, 'text-anchor': 'middle', style: 'font-size:11px;fill:var(--text-3)' });
        s.textContent = opts.centerSub;
        svg.appendChild(s);
      }
    }
  }

  /* --- Progress ring ------------------------------------------------------- */
  function ring(svg, value, opts) {
    opts = opts || {};
    var size = 120, cx = 60, cy = 60, r = 48, thick = opts.thick || 11;
    clear(svg);
    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
    var c = 2 * Math.PI * r;
    svg.appendChild(ns('circle', { cx: cx, cy: cy, r: r, fill: 'none', stroke: 'var(--surface-3)', 'stroke-width': thick }));
    svg.appendChild(ns('circle', {
      cx: cx, cy: cy, r: r, fill: 'none', stroke: opts.color || 'var(--primary)', 'stroke-width': thick,
      'stroke-linecap': 'round', 'stroke-dasharray': c,
      'stroke-dashoffset': c * (1 - Math.min(1, value / 100)),
      transform: 'rotate(-90 ' + cx + ' ' + cy + ')'
    }));
    var t = ns('text', { x: cx, y: cy + 2, 'text-anchor': 'middle', style: 'font-size:21px;font-weight:660;fill:var(--text)' });
    t.textContent = Math.round(value) + '%';
    svg.appendChild(t);
    if (opts.label) {
      var s = ns('text', { x: cx, y: cy + 19, 'text-anchor': 'middle', style: 'font-size:10px;fill:var(--text-3)' });
      s.textContent = opts.label;
      svg.appendChild(s);
    }
  }

  /* --- Declarative mounting ------------------------------------------------ */
  function parseList(s) {
    if (!s) return [];
    return s.split(',').map(function (v) { return parseFloat(v.trim()); });
  }

  function mount(root) {
    var nodes = (root || document).querySelectorAll('[data-chart]');
    Array.prototype.forEach.call(nodes, function (svg) {
      var type = svg.getAttribute('data-chart');
      var vals = parseList(svg.getAttribute('data-values'));
      /* Blanks are kept: a label on every fourth bar is written '0h,,,,4h', and
         dropping the empties would stack all six labels on the first six bars. */
      var labels = (svg.getAttribute('data-labels') || '').split(',');
      if (!labels.some(Boolean)) labels = [];
      var opts = {
        color: svg.getAttribute('data-color') || undefined,
        labels: labels.length ? labels : undefined,
        height: svg.getAttribute('data-height') ? +svg.getAttribute('data-height') : undefined,
        compare: svg.getAttribute('data-compare') ? parseList(svg.getAttribute('data-compare')) : undefined,
        compareColor: svg.getAttribute('data-compare-color') || undefined,
        gap: svg.getAttribute('data-gap') ? +svg.getAttribute('data-gap') : undefined
      };
      if (!vals.length && type !== 'ring') return;
      if (type === 'sparkline') sparkline(svg, vals, opts);
      else if (type === 'area') area(svg, vals, opts);
      else if (type === 'bars') bars(svg, vals, opts);
      else if (type === 'ring') ring(svg, parseFloat(svg.getAttribute('data-value')) || 0, { label: svg.getAttribute('data-label') || '' });
    });
  }

  EC.charts = {
    sparkline: sparkline, area: area, bars: bars, donut: donut, ring: ring,
    mount: mount, redraw: function () { mount(); }
  };

  document.addEventListener('DOMContentLoaded', function () { mount(); });

  /* Charts are drawn 1:1 into their measured width, so a resize needs a redraw.
     Only the declarative [data-chart] nodes can be rebuilt from the DOM; charts
     built imperatively re-mount themselves from their own page script. */
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { mount(); }, 180);
  });
})(window);
