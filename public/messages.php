<?php
/**
 * DevTalk Messages (beta) — a small JavaScript client for the JSON API.
 * All of the work happens in assets/messages.js; this page is only the shell.
 */
require_once __DIR__ . '/../src/helpers.php';

render_header('Messages (beta)');
?>
<h1 class="h3 mb-3">Messages <span class="badge text-bg-warning align-middle">beta</span></h1>

<div id="login-card" class="card shadow-sm mb-4 d-none">
    <div class="card-body">
        <h2 class="h5">Sign in to Messages</h2>
        <p class="text-body-secondary small">Messages uses the new DevTalk API. Sign in with your DevTalk username and password.</p>
        <form id="login-form" class="row g-2">
            <div class="col-sm-4">
                <input class="form-control" name="username" placeholder="Username" autocomplete="username" required>
            </div>
            <div class="col-sm-4">
                <input class="form-control" name="password" type="password" placeholder="Password" autocomplete="current-password" required>
            </div>
            <div class="col-sm-4">
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </div>
        </form>
        <div id="login-error" class="alert alert-danger mt-3 mb-0 d-none"></div>
    </div>
</div>

<div id="inbox" class="d-none">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>Signed in to Messages as <strong id="me-name"></strong> <span id="me-role" class="badge text-bg-secondary"></span></div>
        <button id="logout" class="btn btn-sm btn-outline-secondary" type="button">Sign out of Messages</button>
    </div>
    <div class="row g-3">
        <div class="col-md-5">
            <div id="message-list" class="list-group shadow-sm"></div>
        </div>
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div id="message-empty" class="text-body-secondary">Select a message.</div>
                    <div id="message-view" class="d-none">
                        <h2 id="msg-subject" class="h5"></h2>
                        <p class="small text-body-secondary mb-2">From <strong id="msg-from"></strong> to <strong id="msg-to"></strong> · <span id="msg-date"></span></p>
                        <p id="msg-body" class="mb-0"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/messages.js" defer></script>
<?php
render_footer();
