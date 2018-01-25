// Set the Drupal site path.
var sitePath = location.protocol + '//' + location.hostname + (location.port ? ':' + location.port : '') + drupalSettings.path.baseUrl;
jDrupal.config('sitePath', sitePath);

// Connect to Drupal and say hello to the current user.
jDrupal.connect().then(function() {
  var user = jDrupal.currentUser();
  var msg = '';
  var el = document.getElementById('dg-msg');
  if (user.isAuthenticated()) {
    msg = 'Connection OK';
    el.className = 'messages messages--status'
  }
  else {
    msg = 'Connection Error';
    el.className = 'messages messages--error'
  }
  el.innerHTML = msg;
});
