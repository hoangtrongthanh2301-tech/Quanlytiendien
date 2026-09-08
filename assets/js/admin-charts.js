(function(){
  // admin-charts.js - isolated module to add BigData charts without touching existing globals
  function safeGetContext(id){
    const el = document.getElementById(id);
    if (!el) return null;
    try { return el.getContext('2d'); } catch(e){ return null; }
  }

  function buildDonut(ctx, labels, data, colors){
    if (!ctx || typeof Chart === 'undefined') return null;
    try {
      return new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors || ['#1c4ab2','#3b82f6','#60a5fa','#10b981','#f59e0b'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
      });
    } catch (e) {
      console.error('Error building donut chart', e);
      return null;
    }
  }

  function fetchAnalytics(months){
    const url = `admin-stats.php?type=analytics&months=${encodeURIComponent(months||12)}`;
    return fetch(url, { cache: 'no-store' }).then(r => r.text()).then(text => {
      if (!text) return { success:false, error: 'Empty response' };
      try { return JSON.parse(text); } catch (e) {
        const first = text.indexOf('{');
        const last = text.lastIndexOf('}');
        if (first !== -1 && last !== -1 && last > first) {
          try { return JSON.parse(text.slice(first, last + 1)); } catch (e2) { /* fall through */ }
        }
        return { success:false, error: 'Invalid JSON response' };
      }
    }).catch(err => ({ success:false, error: err && err.message }));
  }

  function renderSegmentDonut(data){
    if (!Array.isArray(data) || data.length === 0) return;
    const labels = data.map(s => s.segment);
    const values = data.map(s => Number(s.customer_count) || 0);
    const colors = ["#1c4ab2","#3b82f6","#60a5fa","#10b981","#f97316"];
    const ctx = safeGetContext('segmentDonutChart');
    if (!ctx) return;
    // create a local chart instance so we don't conflict with existing globals
    buildDonut(ctx, labels, values, colors);
  }

  // Provide a safe, lightweight canvas fallback renderer for the revenue trend chart.
  // This ensures a visual is shown even when Chart.js is blocked or fails to parse.
  if (typeof window.renderRevenueMultiYearFallback !== 'function') {
    window.renderRevenueMultiYearFallback = function(labels, series){
      try {
        const canvas = document.getElementById('revenueTrendChart');
        if (!canvas || !canvas.getContext) return console.warn('No canvas element for revenueTrendChart');
        const ctx = canvas.getContext('2d');
        // normalize input: series can be [{label,data:[]}, ...] or an array of arrays
        let datasets = [];
        if (!series) series = [];
        if (series.length && series[0] && series[0].data) datasets = series;
        else if (series.length && Array.isArray(series[0])) datasets = series.map((d, i) => ({ label: 'S' + (i+1), data: d }));
        else datasets = [{ label: 'Series', data: Array.isArray(series) ? series : [] }];

        const w = canvas.clientWidth || 800;
        const h = Math.max(240, canvas.clientHeight || 300);
        canvas.width = w * (window.devicePixelRatio || 1);
        canvas.height = h * (window.devicePixelRatio || 1);
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.setTransform(window.devicePixelRatio || 1, 0, 0, window.devicePixelRatio || 1, 0, 0);
        ctx.clearRect(0,0,w,h);

        labels = labels || (datasets[0] && datasets[0].data ? datasets[0].data.map((_,i) => String(i+1)) : []);
        const max = Math.max(1, ...datasets.flatMap(ds => ds.data.map(v => Number(v) || 0)));
        const margin = 40;
        const plotW = Math.max(10, w - margin*2);
        const plotH = Math.max(10, h - margin*2);

        // axes
        ctx.strokeStyle = '#d1d5db00';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(margin, margin);
        ctx.lineTo(margin, margin + plotH);
        ctx.lineTo(margin + plotW, margin + plotH);
        ctx.stroke();

        // x labels
        ctx.fillStyle = '#374151';
        ctx.font = '12px Arial';
        const steps = Math.max(1, labels.length - 1);
        labels.forEach((lab, i) => {
          const x = margin + (steps ? (i / steps) * plotW : 0);
          ctx.fillText(lab, x - 10, margin + plotH + 16);
        });

        const colors = ['#1c4ab2','#3b82f6','#60a5fa','#10b981','#f97316','#ef4444'];
        datasets.forEach((ds, idx) => {
          ctx.beginPath();
          ctx.strokeStyle = colors[idx % colors.length];
          ctx.lineWidth = 2;
          ds.data.forEach((v, i) => {
            const val = Number(v) || 0;
            const x = margin + (steps ? (i / steps) * plotW : 0);
            const y = margin + plotH - (val / max) * plotH;
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            // draw small point
            ctx.fillStyle = colors[idx % colors.length];
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, Math.PI * 2);
            ctx.fill();
          });
          ctx.stroke();
        });
      } catch (err) {
        console.warn('renderRevenueMultiYearFallback failed', err);
      }
    };
  }

  function init(){
    if (typeof Chart === 'undefined') {
      // Chart.js not loaded yet - wait a short time
      const waitChart = setInterval(() => {
        if (typeof Chart !== 'undefined') {
          clearInterval(waitChart);
          init();
        }
      }, 200);
      // give up after some time
      setTimeout(() => clearInterval(waitChart), 10000);
      return;
    }

    // Only initialize when analytics panel exists
    document.addEventListener('DOMContentLoaded', function(){
      const panel = document.getElementById('analyticsPanel');
      if (!panel) return;
      // Load analytics and render donut
      fetchAnalytics(12).then(resp => {
        if (!resp || !resp.success) {
          console.warn('admin-charts: analytics not available', resp && resp.error);
          return;
        }
        try { renderSegmentDonut(resp.segments || []); } catch(e){ console.error(e); }
      }).catch(e => console.error('admin-charts analytics fetch error', e));
    });
  }

  init();
})();
