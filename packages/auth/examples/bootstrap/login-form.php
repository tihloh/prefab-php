<?php
/** Copyable Bootstrap 5 login recipe. Project owns route, CSRF and validation. */
?>
<div class="card mx-auto" style="max-width: 420px">
  <div class="card-body">
    <h5 class="card-title mb-3">Sign in</h5>
    <div class="mb-3"><label class="form-label">Email or username</label><input class="form-control" name="identifier" autocomplete="username"></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" autocomplete="current-password"></div>
    <button class="btn btn-primary w-100" type="submit">Sign in</button>
  </div>
</div>
