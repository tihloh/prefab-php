(() => {
  const current = document.body.dataset.page || location.pathname.split('/').pop() || '';

  const integrations = {
    'routes.html': {
      intro: 'Prefab Routes stays independent, but it can carry neutral metadata that other Prefab modules or middleware can interpret. This lets routing cooperate with Auth, Permissions and Logs without making those packages hard dependencies.',
      rows: [
        ['Auth', 'Compatible hook', 'route.auth()', 'Marks a route as requiring authentication in route metadata. An auth middleware or integration layer reads the metadata and enforces the requirement.'],
        ['Permissions', 'Compatible hook', 'route.permission("documents.view")', 'Stores the required permission on the route. Permission middleware can call PermissionManager::can() before the controller runs.'],
        ['Logs', 'Compatible hook', 'route.log("documents.view")', 'Stores the intended audit action on the route. A logging middleware/integration can record it after or around the handler.'],
        ['Auth + Permissions + Logs', 'Composable', 'middleware pipeline', 'A protected route can authenticate the current user, resolve a permission, run the controller and then record activity as one request pipeline.'],
      ],
      note: 'Routes does not silently enforce Auth or Permissions by itself. The route helpers intentionally attach metadata so an application can decide how enforcement should work.'
    },
    'database.html': {
      intro: 'Prefab Database is a shared infrastructure module. Once configured, it publishes a database capability that other compatible Prefab modules can discover and reuse automatically.',
      rows: [
        ['Users', 'Automatic', 'database capability', 'Users can build its PDO-backed provider from the shared database without creating another PDO connection in the Users configuration.'],
        ['Auth', 'Automatic when needed', 'database / PDO capability', 'Auth can use the available database for its own credentials, PINs and login-token tables while leaving the application users table untouched.'],
        ['Permissions', 'Automatic', 'database capability', 'Permissions can create/use its permission store from the shared database.'],
        ['Logs', 'Automatic', 'database capability', 'Logs can create/use its audit-log repository from the shared database.'],
        ['Named connections', 'Automatic capability', 'database.connection.NAME', 'Modules that support a connection name can target a published named connection instead of the default one.'],
      ],
      note: 'This is why a larger Prefab application normally configures Database once. Individual modules can still receive an explicit PDO/store when isolation is preferred.'
    },
    'users.html': {
      intro: 'Prefab Users provides identity/profile data and publishes its user provider so other modules can reuse the same users instead of creating another account system.',
      rows: [
        ['Database', 'Automatic', 'database capability', 'Users can discover the shared database and create its default PDO user provider automatically.'],
        ['Auth', 'Automatic', 'user_provider capability', 'Auth detects Prefab Users and wraps it with the Auth adapter. The same user ID/profile is used for authentication without requiring password fields in the users table.'],
        ['Logs', 'Automatic', 'logger capability', 'When Prefab Logs is available, create(), update() and delete() activity can be recorded automatically from the structured log payload produced by Users.'],
        ['Auth → Users logs', 'Automatic actor context', 'actor_provider capability', 'When Auth is present, Users can use the authenticated user ID as the actor for automatic user-management audit records.'],
        ['Permissions', 'Compatible', 'user ID / groups', 'The user ID can be passed directly to PermissionManager. Group membership can be supplied by the Permissions group service or a custom subject adapter.'],
      ],
      note: 'Users owns profile/identity data. Auth owns credentials. Permissions owns authorization. Logs owns history. Keeping these concerns separate lets them cooperate without duplicating the user record.'
    },
    'input.html': {
      intro: 'Prefab Input is intentionally request-focused. It does not auto-save anything, but its validated values and UploadedFile objects are designed to pass cleanly into other modules.',
      rows: [
        ['Files', 'Direct compatible handoff', 'UploadedFile → FileManager::storeUploaded()', 'Validate an upload with Input, then pass the validated UploadedFile directly to Files for permanent storage.'],
        ['Users', 'Direct compatible handoff', 'validated() → UserManager::create/update()', 'Only validated/whitelisted form fields need to be passed to Users. Raw request fields stay out of persistence.'],
        ['Auth', 'Direct compatible handoff', 'validated identifier/password/PIN', 'A login form can validate/normalize its fields first, then pass them to attempt() or attemptPin().'],
        ['Any module', 'Direct compatible handoff', 'InputResult::validated()', 'Validated input is plain PHP data, so it can safely become service-method arguments throughout the application.'],
      ],
      note: 'Input does not automatically call another module because validation should never silently cause a database write, login or file save.'
    },
    'auth.html': {
      intro: 'Prefab Auth is one of the main interoperability hubs. It can discover the project user provider, reuse shared storage, publish the current actor and send authentication activity to Logs.',
      rows: [
        ['Users', 'Automatic', 'user_provider capability', 'Auth detects Prefab Users and resolves email, username or other configured identifiers against the same user records.'],
        ['Database', 'Automatic when storage is needed', 'database capability', 'Auth uses the shared database for password credentials, PIN credentials and login tokens when no custom stores are supplied.'],
        ['Logs', 'Automatic', 'logger capability', 'Login, logout and authentication events can be recorded by Prefab Logs without manually calling LogManager after every Auth operation.'],
        ['Users / Permissions / other modules', 'Automatic shared actor', 'actor_provider capability', 'Auth publishes itself as the current actor provider. Compatible modules can automatically attach the logged-in user ID to their audit events.'],
        ['Permissions', 'Direct composition', 'auth.id() → permissions.can()', 'Auth answers who is logged in; Permissions answers what that user may do. They remain separate so authentication never implies authorization.'],
        ['Routes', 'Compatible hook', 'route.auth() + middleware', 'Routes can mark endpoints as authenticated and middleware can query Auth before allowing the handler to run.'],
        ['Social login', 'Built-in optional subsystem', 'SocialAuthManager', 'OAuth/social providers establish the same local Auth session, so the rest of the app does not care whether login came from password, PIN, token or social provider.'],
      ],
      note: 'Automatic discovery remains overridable. Supplying an explicit provider, store or session implementation takes priority when a project needs custom behavior.'
    },
    'permissions.html': {
      intro: 'Prefab Permissions can reuse shared infrastructure and automatically participate in audit history while remaining independent from Auth and Users.',
      rows: [
        ['Database', 'Automatic', 'database capability', 'Permissions can create/use its PDO permission store from the shared database when no explicit store is supplied.'],
        ['Auth', 'Automatic actor context', 'actor_provider capability', 'Permission grant/deny/clear events can identify the currently authenticated user as the actor through the shared Auth capability.'],
        ['Logs', 'Automatic', 'PrefabRuntime log emission', 'set() and clear() generate structured permission-change events that Prefab Logs can record when the logger capability is present.'],
        ['Users', 'Direct composition', 'user ID / PermissionSubjectInterface', 'A Prefab user ID can be checked directly, or an application subject can expose user and group IDs through PermissionSubjectInterface.'],
        ['Routes', 'Compatible hook', 'route.permission()', 'Routes can attach a permission key as metadata and middleware can enforce it with PermissionManager::can().'],
        ['Groups', 'Built-in', 'GroupManager', 'Group membership and group permission overrides are part of the Permissions package, allowing reusable authorization rules across users.'],
      ],
      note: 'Permissions does not need Auth in order to work. Auth simply improves context by providing the current actor and current user for typical web applications.'
    },
    'logs.html': {
      intro: 'Prefab Logs is designed to coexist with the other modules. When it is installed and configured, it publishes a logger capability that compatible modules can discover, so normal module operations can produce audit records without repeating LogManager::record() throughout application code.',
      rows: [
        ['Users', 'Automatic logging', 'user.created / user.updated / user.deleted', 'UserManager produces structured changes and sends them to the discovered logger. Actor information can come automatically from Auth.'],
        ['Auth', 'Automatic logging', 'auth.* events', 'Authentication activity can be sent to Logs automatically when Auth discovers the logger capability.'],
        ['Permissions', 'Automatic logging', 'permission.granted / denied / cleared', 'Permission changes emit structured log payloads that the Prefab runtime can forward to Logs.'],
        ['Database', 'Automatic storage reuse', 'database capability', 'Logs can build its PDO repository from the shared database without a separate database setup.'],
        ['Other modules / app code', 'Compatible', 'logger capability / record()', 'Any module can emit a compatible structured payload, or application code can call record() directly for domain-specific events.'],
        ['Human presentation', 'Built-in', 'human() / humanRecent()', 'Stored technical events can be converted into human-friendly activity text without changing the raw audit record.'],
      ],
      note: 'Automatic logging only occurs for modules that actually emit compatible log events. Domain-specific actions in application code should still call Logs or emit their own structured event.'
    },
    'files.html': {
      intro: 'Prefab Files stays storage-focused, but its APIs are intentionally shaped to work with validated uploads, routes and application authorization without owning those concerns.',
      rows: [
        ['Input', 'Direct compatible handoff', 'Input UploadedFile → storeUploaded()', 'Input validates the incoming upload; Files permanently stores the same normalized object.'],
        ['Routes', 'Direct composition', 'download() / temporaryUrl()', 'A route/controller can use FileDownload to stream files or verify signed temporary URLs without Files forcing an HTTP framework.'],
        ['Auth + Permissions', 'Direct composition', 'authorize before read/download', 'Private-file routes can ask Auth/Permissions before calling Files. Files deliberately does not decide who may access a document.'],
        ['Logs', 'Hook-based composition', 'on(stored/deleted/copied/moved)', 'Lifecycle hooks can record storage activity through Prefab Logs when an application wants file-level audit history.'],
      ],
      note: 'Files does not automatically log or authorize operations because storage can also be used in CLI jobs, background tasks and public asset workflows where those policies differ.'
    },
    'notifications.html': {
      intro: 'Prefab Notifications represents in-application notifications. It remains separate from message delivery so a project can choose whether a notification also sends email, SMS or another channel.',
      rows: [
        ['Messaging', 'Direct composition', 'notification + message', 'Application code can create an in-app notification and also send a message through Messaging when the event should reach the user outside the application.'],
        ['Users', 'Direct composition', 'recipientId = user ID', 'A Prefab user ID can be used as the notification recipient identifier. Notifications does not require the Users package.'],
        ['Auth', 'Direct composition', 'auth.id()', 'The current authenticated user ID can be used to load unread notifications and counters.'],
        ['Database', 'Explicit store', 'PdoNotificationStore', 'Persistent notifications use the PDO store. Unlike modules with PrefabRuntime auto-discovery, the current Notifications package receives its store explicitly.'],
      ],
      note: 'Notifications intentionally does not auto-send email. An in-app notification and an external message are different delivery decisions.'
    },
    'messaging.html': {
      intro: 'Prefab Messaging is the outbound-delivery layer. It can be combined with any module that produces an event, but it does not silently send messages when those modules change data.',
      rows: [
        ['Notifications', 'Direct composition', 'send both when needed', 'Create an in-app notification and send an external email/message from the same application action when both channels are appropriate.'],
        ['Users', 'Direct composition', 'user email → Recipient::email()', 'A user profile email can become a mail recipient without Messaging depending directly on Prefab Users.'],
        ['Auth', 'Direct composition', 'security messages', 'Password-reset, login-token or security flows can send links/tokens through registered Messaging channels.'],
        ['Logs', 'Hook-based composition', 'on(sending/sent/failed)', 'Messaging lifecycle hooks can be recorded in Prefab Logs when delivery auditing is required.'],
        ['Custom providers', 'Built-in extension', 'ChannelInterface', 'Email, SMS, chat, push or another delivery service can be added as a channel while the application keeps using MessagingManager::send().'],
      ],
      note: 'Messaging does not auto-send on Users/Auth/Notifications events. External communication is a side effect that applications should opt into deliberately.'
    },
  };

  const data = integrations[current];
  const article = document.querySelector('main article');
  if (!data || !article || article.querySelector('.interop-reference')) return;

  const section = document.createElement('section');
  section.className = 'interop-reference';
  section.innerHTML = `
    <h2>Works with other Prefab modules</h2>
    <p>${data.intro}</p>
    <div class="api-table-wrap">
      <table class="api-table interop-table">
        <thead><tr><th>Module</th><th>Integration</th><th>How</th><th>What happens</th></tr></thead>
        <tbody>${data.rows.map(row => `<tr><td><code>${row[0]}</code></td><td><strong>${row[1]}</strong></td><td><code>${row[2]}</code></td><td>${row[3]}</td></tr>`).join('')}</tbody>
      </table>
    </div>
    <div class="callout"><b>Important:</b> ${data.note}</div>`;

  const api = article.querySelector('.api-reference');
  if (api) article.insertBefore(section, api);
  else {
    const navs = article.querySelectorAll('.page-nav');
    const bottomNav = navs.length ? navs[navs.length - 1] : null;
    if (bottomNav) article.insertBefore(section, bottomNav);
    else article.appendChild(section);
  }
})();
