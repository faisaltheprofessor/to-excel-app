@if(app()->hasDebugModeEnabled() || config('app.debug'))
<script>
(function() {
  function logErr(prefix, e, extra) {
    try {
      console.group(prefix);
      if (e && e.message) console.log('message:', e.message);
      if (e && e.filename) console.log('filename:', e.filename);
      if (e && typeof e.lineno !== 'undefined') console.log('line:', e.lineno, 'col:', e.colno);
      if (e && e.error && e.error.stack) console.log('stack:', e.error.stack);
      if (extra) console.log('extra:', extra);
      console.groupEnd();
    } catch(_) {}
  }

  // Syntax/runtime errors from scripts
  window.addEventListener('error', function(e) {
    logErr('window.error', e, null);
  });

  // Promise rejections
  window.addEventListener('unhandledrejection', function(e) {
    logErr('unhandledrejection', e.reason || e, null);
  });

  // Inspect inline <script> tags for stray "@"
  setTimeout(function() {
    try {
      var scripts = document.getElementsByTagName('script');
      for (var i=0; i<scripts.length; i++) {
        var s = scripts[i];
        var src = s.getAttribute('src');
        var type = (s.getAttribute('type') || 'text/javascript').toLowerCase();
        if (!src && type.indexOf('javascript') !== -1) {
          var txt = s.textContent || '';
          if (txt.indexOf('@') !== -1) {
            console.warn('Found "@" in inline <script> #' + i + ' (check your Blade directives weren\'t left unprocessed). Snippet:', txt.slice(0, 150));
          }
        }
      }
    } catch (scanErr) { console.warn('script scan failed', scanErr); }
  }, 0);
})();
</script>
@endif
