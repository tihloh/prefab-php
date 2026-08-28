const pages=[
['INTRODUCTION','index.html','Introduction'],
['INTRODUCTION','comparison.html','With vs Without Prefab'],
['INTRODUCTION','how-it-works.html','How Prefab Works'],
['GETTING STARTED','prerequisites.html','Prerequisites'],
['GETTING STARTED','install.html','Install PHP & Composer'],
['GETTING STARTED','packages.html','Package Availability'],
['GETTING STARTED','project.html','Create a Project'],
['TUTORIAL','routes.html','1. Routing'],
['TUTORIAL','database.html','2. Database'],
['TUTORIAL','users.html','3. Users & Groups'],
['TUTORIAL','input.html','4. Input & Validation'],
['TUTORIAL','auth.html','5. Authentication'],
['TUTORIAL','permissions.html','6. Permissions'],
['TUTORIAL','logs.html','7. Audit Logs'],
['TUTORIAL','files.html','8. Files'],
['TUTORIAL','notifications.html','9. Notifications'],
['TUTORIAL','messaging.html','10. Messaging'],
['REFERENCE','config.html','Shared Configuration'],
['REFERENCE','sessions.html','Session Isolation'],
['REFERENCE','autowiring.html','Auto-Wiring'],
['REFERENCE','troubleshooting.html','Troubleshooting'],
['REFERENCE','production.html','Production Checklist']
];
const current=document.body.dataset.page||location.pathname.split('/').pop()||'index.html';
const sidebar=document.getElementById('sidebar');
let section='';
pages.forEach(([group,file,label])=>{if(group!==section){section=group;const h=document.createElement('div');h.className='side-title';h.textContent=group;sidebar.appendChild(h);}const a=document.createElement('a');a.href=file;a.textContent=label;if(file===current)a.classList.add('active');a.addEventListener('click',()=>sidebar.classList.remove('open'));sidebar.appendChild(a);});
document.getElementById('menu')?.addEventListener('click',()=>sidebar.classList.toggle('open'));
const i=pages.findIndex(p=>p[1]===current);const prev=i>0?pages[i-1]:null;const next=i>=0&&i<pages.length-1?pages[i+1]:null;
function nav(){const n=document.createElement('nav');n.className='page-nav';if(prev)n.innerHTML+=`<a class="prev" href="${prev[1]}">← ${prev[2]}</a>`;else n.innerHTML+='<span></span>';if(next)n.innerHTML+=`<a class="next" href="${next[1]}">${next[2]} →</a>`;return n;}
const article=document.querySelector('main article');if(article){article.prepend(nav());article.append(nav());}

// Public application-facing interfaces audited from the current package source.
const apiReferences={
'routes.html':{
  intro:'These are the public routing interfaces available to application code. RouteManager registers and dispatches routes; Route configures one route; RouteMatch describes a successful match.',
  rows:[
    ['RouteManager','get(path, handler)','Register a GET route. Returns the Route so it can be configured fluently.'],
    ['RouteManager','post(path, handler)','Register a POST route.'],['RouteManager','put(path, handler)','Register a PUT route.'],['RouteManager','patch(path, handler)','Register a PATCH route.'],['RouteManager','delete(path, handler)','Register a DELETE route.'],['RouteManager','any(path, handler)','Register the common HTTP methods on one route.'],['RouteManager','matchMethods(methods, path, handler)','Register only a chosen list of HTTP methods.'],['RouteManager','add(methods, path, handler)','Low-level route registration used when method helpers are not enough.'],['RouteManager','group(options, callback)','Apply a shared prefix, name prefix, middleware and metadata to several routes.'],['RouteManager','middleware(name, handler)','Register reusable route middleware by name.'],['RouteManager','fallback(handler)','Choose the handler used when no route matches.'],['RouteManager','redirect(from, to, status)','Register a redirect route.'],['RouteManager','resource(path, controller)','Generate conventional CRUD routes for a controller.'],['RouteManager','load(file)','Load route definitions from a PHP file that returns an array.'],['RouteManager','match(method, path)','Find a matching route without executing its handler. Returns RouteMatch or null.'],['RouteManager','dispatch(method?, path?)','Match a request, run middleware and execute the route handler.'],['RouteManager','url(name, parameters)','Generate a URL from a named route. Extra parameters become a query string.'],['RouteManager','all()','Return every registered Route object.'],['RouteManager','find(name)','Find a route by its route name.'],['RouteManager','byTag(tag)','Return routes carrying a particular tag.'],['RouteManager','toArray()','Export all registered routes as arrays.'],['RouteManager','table()','Return a compact developer-friendly route table.'],['RouteManager','explain()','Return diagnostics such as route counts, middleware names and conflicts.'],
    ['Route','name(name)','Assign a stable name used by find() and url().'],['Route','middleware(...names)','Attach one or more registered middleware names.'],['Route','where(parameter, pattern)','Restrict a route parameter with a regular-expression fragment.'],['Route','defaults(values)','Supply values for missing optional parameters.'],['Route','meta(values)','Attach neutral application metadata to the route.'],['Route','tag(...tags)','Classify a route for inspection or tooling.'],['Route','auth(required=true)','Mark the route as requiring authentication metadata.'],['Route','permission(permission)','Attach a permission requirement as metadata.'],['Route','log(action)','Attach an audit/log action as metadata.'],['Route','methods(), path(), handler()','Read the route HTTP methods, path and handler.'],['Route','routeName(), middlewareList()','Read the configured name and middleware list.'],['Route','constraints(), defaultValues()','Read parameter constraints and defaults.'],['Route','metadata(), tags()','Read metadata and tags.'],['Route','toArray()','Export one route as an array.'],
    ['RouteMatch','route()','Return the matched Route object.'],['RouteMatch','handler()','Return the matched route handler.'],['RouteMatch','parameters()','Return parameters extracted from the request path.']
  ]},
'database.html':{
  intro:'DatabaseManager manages connections and raw SQL. table() returns QueryBuilder for common CRUD. Both are public APIs.',
  rows:[
    ['DatabaseManager','prefabConfigure()','Resolve configured connections and publish Prefab database capabilities. Usually called by Prefab bootstrap.'],['DatabaseManager','connection(name?)','Return the raw PDO connection by name or the default connection.'],['DatabaseManager','get(name?)','Backward-compatible alias of connection().'],['DatabaseManager','default()','Return the default PDO connection.'],['DatabaseManager','defaultName()','Return the current default connection name.'],['DatabaseManager','driver(name?)','Return the normalized database driver name.'],['DatabaseManager','has(name)','Check whether a named connection exists.'],['DatabaseManager','names()','List configured connection names.'],['DatabaseManager','table(table, connection?)','Start a QueryBuilder for a table.'],['DatabaseManager','select(sql, bindings, connection?)','Run parameterized SELECT SQL and return associative rows.'],['DatabaseManager','statement(sql, bindings, connection?)','Run a parameterized non-SELECT SQL statement.'],['DatabaseManager','transaction(callback, connection?)','Run work atomically and automatically commit or roll back.'],['DatabaseManager','lastInsertId(name?)','Return PDO lastInsertId() for the default connection.'],['DatabaseManager','pdo()','Raw PDO escape hatch for the default connection.'],['DatabaseManager','set(name, definition)','Add or replace a connection at runtime.'],['DatabaseManager','useDefault(name)','Switch which configured connection is the default.'],['DatabaseManager','ping(name?)','Test whether a connection can execute SELECT 1.'],['DatabaseManager','prefabResource(name)','Expose database capabilities for Prefab integration.'],['DatabaseManager','explain()','Show how database configuration/capabilities were resolved.'],
    ['QueryBuilder','where(column, value) / where(column, operator, value)','Add an AND filter with bound values.'],['QueryBuilder','orderBy(column, direction)','Add ASC or DESC ordering.'],['QueryBuilder','limit(number)','Limit returned rows.'],['QueryBuilder','offset(number)','Skip rows before returning results.'],['QueryBuilder','get()','Execute SELECT and return all rows.'],['QueryBuilder','first()','Return the first row or null.'],['QueryBuilder','insert(values)','Insert one row and return success.'],['QueryBuilder','insertGetId(values)','Insert one row and return the generated ID.'],['QueryBuilder','update(values)','Update matching rows and return affected-row count.'],['QueryBuilder','delete()','Delete matching rows and return affected-row count.']
  ]},
'users.html':{
  intro:'UserManager is the normal application API. PrefabUser is the returned user object. Provider/factory contracts are extension points for custom storage or user objects.',
  rows:[
    ['UserManager','prefabConfigure()','Resolve provider/database capabilities and publish the user provider.'],['UserManager','prefabResource(name)','Expose resolved Users resources to Prefab integrations.'],['UserManager','explain()','Inspect how Users resolved its provider, table and database.'],['UserManager','useContext(context)','Attach reusable log/request context to write operations.'],['UserManager','useEvents(events)','Attach an event dispatcher for emitted log payloads.'],['UserManager','find(id)','Load one user by primary identifier.'],['UserManager','findByEmail(email)','Load one user by email.'],['UserManager','all(limit=100, offset=0)','Return a paginated list of users.'],['UserManager','create(data, context=[])','Create a user and return OperationResult containing the user and log payload.'],['UserManager','update(id, data, context=[])','Update a user and return OperationResult.'],['UserManager','delete(id, context=[])','Delete a user when the map permits it and return OperationResult.'],
    ['PrefabUser','id, name, email, active','Core normalized user properties available to application code.'],['PrefabUser','get(name, default?)','Read a mapped custom attribute.'],['PrefabUser','has(name)','Check whether a custom attribute exists.'],['PrefabUser','attributes()','Return all mapped custom attributes.'],['PrefabUser','toArray()','Export core fields and mapped attributes as an array.'],
    ['UserProviderInterface','find(), findByEmail(), all()','Read operations a custom provider must implement.'],['UserProviderInterface','create(), update(), delete()','Write operations a custom provider must implement.'],['UserFactoryInterface','make(id, name, email, active, attributes=[])','Extension point for creating the normalized PrefabUser returned by a custom/provider implementation.']
  ]},
'input.html':{
  intro:'Input converts raw request data into processed and validated data. InputResult is the immutable result. UploadedFile represents normalized incoming uploads.',
  rows:[
    ['Input','from(data, files=[])','Create an Input object from explicit arrays and optional PHP-style file data.'],['Input','fromRequest()','Read the current JSON, form-urlencoded or multipart PHP request automatically.'],['Input','data(data)','Replace the raw input data on an existing Input object.'],['Input','rule(name, validator)','Register a custom validation rule. Return an error message from the callback when invalid.'],['Input','transform(name, transformer)','Register a custom value transformer/caster.'],['Input','attributes(labels)','Set human-friendly field labels used in validation messages.'],['Input','messages(messages)','Override validation error messages globally or per field/rule.'],['Input','process(schema)','Apply transforms and validation rules and return InputResult.'],
    ['Built-in transforms','trim, lowercase, uppercase, null_if_empty','Normalize strings before validation/persistence.'],['Built-in casts','string, integer, float, boolean, array','Convert acceptable values to application types.'],['Control rules','sometimes, nullable, default:value','Control whether/how missing or empty fields are processed.'],['Presence rules','required, required_if, required_with','Require values unconditionally or based on related fields.'],['Type/format rules','email, url, string, integer, float, numeric, boolean, array, date','Validate common value types and formats.'],['Size/value rules','min, max, between, in, not_in','Validate numeric/string/array size or allowed values.'],['Comparison rules','same, different, confirmed, distinct, regex','Compare fields, validate confirmations, uniqueness or custom patterns.'],['Upload rules','file, image, mimes, mimetypes, min_size, max_size, dimensions','Validate normalized UploadedFile objects and images.'],
    ['InputResult','passes() / fails()','Check whether the schema produced validation errors.'],['InputResult','raw()','Return untouched input for diagnostics. Do not treat it as trusted data.'],['InputResult','all()','Return all processed/transformed values, including fields that may have errors.'],['InputResult','validated(field?, default?)','Return the validated whitelist, or one validated field.'],['InputResult','errors()','Return all field validation errors.'],['InputResult','first(field?)','Return the first error globally or for one field.'],['InputResult','value(field, default?)','Read one processed value with dot-path support.'],
    ['UploadedFile','name(), tmpPath(), error(), size(), clientType()','Read normalized upload metadata.'],['UploadedFile','mime(), extension()','Read detected MIME and original extension.'],['UploadedFile','isValid(), isImage(), dimensions()','Check upload validity and image dimensions.'],['UploadedFile','matchesExtension(allowed), matchesMime(allowed)','Safely test allowed file extensions/MIME patterns.']
  ]},
'auth.html':{
  intro:'AuthManager handles local authentication and sessions. SocialAuthManager handles OAuth/social flows. Store/provider contracts let projects replace identity and credential storage.',
  rows:[
    ['AuthManager','prefabConfigure()','Resolve session, user provider, password/PIN/token stores and integrations.'],['AuthManager','explain()','Inspect Auth configuration and capability resolution.'],['AuthManager','useContext(context)','Attach reusable request/log context.'],['AuthManager','useEvents(events)','Attach an event dispatcher for authentication logs.'],['AuthManager','setPassword(userId, password)','Hash and assign a password in Auth-owned credential storage.'],['AuthManager','hasPassword(userId)','Check whether a user has a password credential.'],['AuthManager','verifyPassword(userId, password)','Verify a password without logging the user in.'],['AuthManager','changePassword(userId, current, new)','Verify the old password and replace it with a new hash.'],['AuthManager','removePassword(userId)','Remove the user password credential.'],['AuthManager','setPin(userId, pin)','Validate, hash and store a PIN.'],['AuthManager','hasPin(userId)','Check whether the user has a PIN.'],['AuthManager','verifyPin(userId, pin)','Verify a PIN without changing the current session.'],['AuthManager','removePin(userId)','Remove the user PIN.'],['AuthManager','attempt(identifier, password, context=[])','Authenticate with password using the configured identifier policy.'],['AuthManager','attemptPin(identifier, pin, context=[])','Authenticate with a PIN.'],['AuthManager','createLoginToken(userId, ttl?)','Issue a short-lived single-use login token.'],['AuthManager','createNextLoginToken(userId, ttl?)','Issue a single-use token intended for the next login.'],['AuthManager','createRememberToken(userId, ttl?)','Issue a reusable persistent remember-login token.'],['AuthManager','attemptToken(token, purpose="login", context=[])','Authenticate a login, next-login or remember token.'],['AuthManager','revokeLoginTokens(userId, purpose?)','Revoke all or selected login tokens for a user.'],['AuthManager','attemptFromQuery(query, context=[])','Process opt-in URL token/password login. Disabled by default.'],['AuthManager','login(user, context=[])','Directly establish a session for an already verified active identity.'],['AuthManager','logout(context=[])','Clear the normal authenticated session and produce a log result.'],['AuthManager','check()','Return whether a normal Auth session is active.'],['AuthManager','id()','Return the authenticated user ID or null.'],['AuthManager','user()','Reload and return the authenticated user identity.'],
    ['SocialAuthManager','authorizationUrl(provider)','Create provider authorization URL with state protection.'],['SocialAuthManager','callback(provider, query, context=[])','Validate callback, resolve/link local user and establish Auth session.'],['SocialAuthManager','link(userId, provider, query, context=[])','Link an additional social account to an existing local user.'],['SocialAuthManager','unlink(userId, provider, context=[])','Remove a social-provider link.'],['SocialAuthManager','accountsForUser(userId)','List social accounts linked to a local user.'],['SocialAuthManager','providers()','List configured social provider names.'],
    ['AuthUserProviderInterface','findByIdentifier(), findById()','Contract for resolving login identifiers and user IDs.'],['AuthenticatableUserInterface','authId(), authPasswordHash(), authIsActive()','Minimal identity contract used by Auth. Password hash may be null when Auth-owned storage is used.'],['AuthCredentialStoreInterface','passwordHash(), setPasswordHash(), remove()','Custom password credential storage contract.'],['PinCredentialStoreInterface','pinHash(), setPinHash(), removePin()','Custom PIN storage contract.'],['LoginTokenStoreInterface','issue(), consume(), revokeForUser()','Custom login/remember token storage contract.']
  ]},
'permissions.html':{
  intro:'PermissionManager resolves authorization. GroupManager manages reusable groups and memberships. The contracts and result objects are available for custom integrations.',
  rows:[
    ['PermissionManager','prefabConfigure()','Resolve permission definitions, store and database capability.'],['PermissionManager','prefabResource(name)','Expose resolved permission resources to Prefab.'],['PermissionManager','explain()','Inspect how permission definitions and storage were resolved.'],['PermissionManager','useContext(context)','Attach common log/request context.'],['PermissionManager','useEvents(events)','Attach an event dispatcher for permission-change logs.'],['PermissionManager','can(subject, permission, groupIds=[])','Return only true/false for the effective permission.'],['PermissionManager','resolve(subject, permission, groupIds=[])','Return PermissionResult with allowed value and source (user/group/default/unknown).'],['PermissionManager','overridesFor(type, id)','Read the raw overrides stored for one user/group subject.'],['PermissionManager','resolvedFor(subject, groups=[])','Resolve every defined permission for a subject.'],['PermissionManager','set(type, id, permission, value, context=[])','Create/update an explicit allow or deny override.'],['PermissionManager','clear(type, id, permission, context=[])','Remove one override so resolution can inherit again.'],['PermissionManager','clearAll(type, id)','Remove all overrides for a subject.'],['PermissionManager','definitions()','Return all permission definitions.'],['PermissionManager','definition(permission)','Return one definition or null.'],['PermissionManager','defined(permission)','Check whether a permission key exists.'],
    ['GroupManager','all()','List groups including user counts.'],['GroupManager','find(id)','Find one group.'],['GroupManager','create(name, description?, permissionOverrides?, context=[])','Create a group and optionally assign permission overrides.'],['GroupManager','update(id, data, context=[])','Change group details and/or its permission overrides.'],['GroupManager','delete(id, context=[])','Delete a group and clear its permission overrides.'],['GroupManager','userIds(groupId)','List user IDs belonging to a group.'],['GroupManager','groupIdsForUser(userId)','List the groups assigned to a user.'],['GroupManager','syncUserGroups(userId, groupIds, context=[])','Replace a user’s group memberships atomically.'],
    ['PermissionSubjectInterface','permissionSubjectId(), permissionGroupIds()','Allows an object to supply its own user ID and groups to the resolver.'],['PermissionStoreInterface','get(), put(), remove()','Storage contract for permission overrides.'],['PermissionResult','allowed, source, groups, denyingGroups','Explains the effective authorization decision, not just true/false.']
  ]},
'logs.html':{
  intro:'LogManager is the normal audit-log API. LogEntry represents structured input; repository and presenter APIs support custom persistence and human-readable output.',
  rows:[
    ['LogManager','prefabConfigure()','Resolve/publish log repository and database capabilities.'],['LogManager','explain()','Inspect repository/database resolution.'],['LogManager','record(entry)','Persist a LogEntry or compatible array and return its ID.'],['LogManager','find(id)','Find one stored log record.'],['LogManager','recent(limit=100, offset=0)','Return recent log records.'],['LogManager','forSubject(type, id, limit=100)','Return history for one business subject such as a document or user.'],['LogManager','forActor(actorId, limit=100)','Return actions performed by one actor.'],['LogManager','humanRecent(limit, offset, actorResolver?, subjectResolver?)','Return recent logs transformed into human-friendly presentation data.'],['LogManager','human(log, actorResolver?, subjectResolver?)','Present one raw log record in human-friendly form.'],
    ['LogRepositoryInterface','record(), find(), recent(), forSubject(), forActor()','Contract for replacing PDO persistence with another repository.'],['LogEntry','fromArray(data)','Create a normalized structured log entry from an array.'],['LogEntry','action / subjectType / subjectId / actorType / actorId','Core audit identity fields.'],['LogEntry','message / changes / metadata / ipAddress / userAgent / createdAt','Descriptive, structured change and request context fields.'],['HumanLogPresenter','present(), many()','Convert raw logs into readable presentation values, optionally resolving actor/subject names.']
  ]},
'files.html':{
  intro:'FileManager is the high-level storage API. DiskInterface is the adapter contract. FileInfo and FileDownload are returned value objects.',
  rows:[
    ['FileManager','add(name, disk)','Register a named storage disk.'],['FileManager','hasDisk(name), names()','Inspect registered disks.'],['FileManager','useDefault(name), defaultName()','Switch/read the default disk.'],['FileManager','disk(name?)','Access the selected DiskInterface directly.'],['FileManager','on(event, listener)','Listen for stored, deleted, copied and moved lifecycle events.'],['FileManager','put(path, contents, disk/options?, options=[])','Store string contents. Supports collision policies.'],['FileManager','putStream(path, stream, disk/options?, options=[])','Store from a readable stream without loading the whole file into memory.'],['FileManager','putFile(sourcePath, targetPath, disk/options?, options=[])','Copy an existing local file into managed storage.'],['FileManager','storeUploaded(upload, directory, name?, disk/options?, options=[])','Store an upload-like object such as Prefab Input UploadedFile.'],['FileManager','read(path, disk?)','Read a file into a string.'],['FileManager','readStream(path, disk?)','Open a readable file stream.'],['FileManager','exists(path, disk?)','Check whether a file exists.'],['FileManager','download(path, filename?, inline?, disk?)','Create a FileDownload object for HTTP streaming.'],['FileManager','delete(path, disk?)','Delete a stored file.'],['FileManager','copy(from, to, disk/options?, options=[])','Copy a file on a disk.'],['FileManager','move(from, to, disk/options?, options=[])','Move/rename a file on a disk.'],['FileManager','info(path, disk?)','Return FileInfo metadata.'],['FileManager','checksum(path, algorithm="sha256", disk?)','Calculate a file checksum.'],['FileManager','size(path, disk?)','Return file size in bytes.'],['FileManager','directorySize(directory, disk?)','Calculate total bytes in a directory.'],['FileManager','usage(disk?)','Return total disk-root usage.'],['FileManager','path(path, disk?)','Return the disk-specific physical/absolute path when supported.'],['FileManager','url(path, disk?)','Return a public URL when the disk provides one.'],['FileManager','supports(capability, disk?)','Ask whether the disk supports a capability.'],['FileManager','files(directory, recursive, disk?)','List files.'],['FileManager','directories(directory, recursive, disk?)','List directories.'],['FileManager','directoryExists(directory, disk?)','Check whether a directory exists.'],['FileManager','makeDirectory(directory, disk?)','Create a directory.'],['FileManager','deleteDirectory(directory, recursive, disk?)','Delete a directory.'],['FileManager','temporaryUrl(path, expires, disk?)','Create a signed temporary application URL for a private file.'],['FileManager','verifyTemporaryUrl(path, expires, signature, disk?)','Verify temporary URL signature, expiry and file existence.'],['FileManager','uniqueName(extension?)','Generate a cryptographically random storage filename.'],
    ['FileInfo','path(), name(), filename(), directory(), extension()','Read normalized path/name metadata.'],['FileInfo','size(), mime(), modifiedAt(), url(), checksum()','Read stored-file metadata.'],['FileInfo','toArray()','Export all FileInfo fields.'],['DiskInterface','put/read/stream/exists/delete/copy/move/info/checksum/...','Contract implemented by storage adapters such as LocalDisk.'],['FileDownload','stream(), filename(), size(), mime(), inline()','Read the download stream and response metadata.'],['FileDownload','headers()','Return Content-Type, Content-Length and Content-Disposition headers for the host HTTP layer.']
  ]},
'notifications.html':{
  intro:'NotificationManager is the application API. Notification is the value object. NotificationStoreInterface allows persistent or custom stores.',
  rows:[
    ['NotificationManager','send(recipientId, title, message, metadata=[], actionUrl=null)','Create a notification and return the stored Notification.'],['NotificationManager','recent(recipientId, limit=20)','Return recent notifications for one recipient.'],['NotificationManager','unread(recipientId, limit=20)','Return only unread notifications.'],['NotificationManager','unreadCount(recipientId)','Return the unread badge/count value.'],['NotificationManager','markRead(notificationId, readAt?)','Mark a notification as read, optionally at a supplied Unix timestamp.'],['NotificationManager','markUnread(notificationId)','Clear read state.'],['NotificationManager','delete(notificationId)','Delete a notification.'],
    ['Notification','id, recipientId, title, message','Core notification fields.'],['Notification','metadata, actionUrl, createdAt, readAt','Optional structured data, destination URL and state timestamps.'],['Notification','isRead() / isUnread()','Check the notification read state from readAt.'],['NotificationStoreInterface','create(), recent(), unreadCount(), markRead(), markUnread(), delete()','Storage contract implemented by in-memory and PDO stores.'],['InMemoryNotificationStore','NotificationStoreInterface implementation','Useful for unit tests or one-process experiments; data disappears with the process.'],['PdoNotificationStore','NotificationStoreInterface implementation','Persistent database-backed notification storage with configurable table/columns.']
  ]},
'messaging.html':{
  intro:'MessagingManager routes a Message to a registered ChannelInterface. Recipient, Message, Attachment, Template and DeliveryResult are public value/helper objects.',
  rows:[
    ['MessagingManager','channel(channel)','Register or replace a named messaging channel.'],['MessagingManager','hasChannel(name)','Check whether a channel is registered.'],['MessagingManager','send(channel, recipient, message)','Send through a named channel and return DeliveryResult.'],['MessagingManager','mail(to, subject, body, html?)','Convenience method for a registered mail channel.'],['MessagingManager','on(event, listener)','Listen for sending, sent or failed lifecycle events.'],
    ['Recipient','email(email, name?)','Create a recipient routed to the mail channel.'],['Recipient','route(channel)','Return the destination value configured for a channel.'],['Recipient','id, name, routes','Public recipient identity/routing data.'],
    ['Message','make(text, subject)','Convenience factory for a simple text message.'],['Message','withAttachment(attachment)','Return a new immutable Message with one more attachment.'],['Message','subject, text, html, metadata, cc, bcc, replyTo, attachments','Public immutable message data.'],
    ['Attachment','fromPath(path, name?, mime?)','Create an attachment from a file path.'],['Attachment','fromData(contents, name, mime?)','Create an in-memory attachment.'],['Attachment','data()','Return attachment bytes, reading the source file when necessary.'],['Attachment','path, contents, name, mime','Public immutable attachment source/name/type data.'],
    ['Template','render(data)','Replace template placeholders and return a Message.'],['DeliveryResult','successful, channel, messageId, error, metadata','Read delivery success/failure and provider result information.'],['DeliveryResult','failed()','Convenience check that returns the opposite of successful.'],
    ['ChannelInterface','name(), send(recipient, message)','Contract for custom SMS, chat, push or other channels.'],['CallableChannel','ChannelInterface implementation','Wrap a PHP callable as a channel; useful for tests and lightweight integrations.'],['MailChannel','ChannelInterface implementation','Routes mail messages through a MailTransportInterface.'],['MailTransportInterface','send(recipient, message)','Contract for mail transports.'],['NativeMailTransport','MailTransportInterface implementation','Send through PHP native mail facilities.'],['SmtpTransport','MailTransportInterface implementation','Send directly through a configured SMTP server.']
  ]}
};

function addApiReference(){
  const definition=apiReferences[current];
  if(!article||!definition) return;
  const wrap=document.createElement('section');
  wrap.className='api-reference';
  const heading=document.createElement('h2');
  heading.textContent='Available API and interfaces';
  wrap.appendChild(heading);
  const intro=document.createElement('p');
  intro.textContent=definition.intro;
  wrap.appendChild(intro);
  const note=document.createElement('p');
  note.className='api-reference-note';
  note.innerHTML='<b>How to read this table:</b> the first column shows the class or interface that owns the API, the second shows what application code can call or access, and the third explains when and why it is useful.';
  wrap.appendChild(note);
  const scroll=document.createElement('div');
  scroll.className='api-table-wrap';
  const table=document.createElement('table');
  table.className='api-table';
  table.innerHTML='<thead><tr><th>Class / interface</th><th>Available API</th><th>What it is for</th></tr></thead>';
  const body=document.createElement('tbody');
  definition.rows.forEach(([owner,api,use])=>{
    const tr=document.createElement('tr');
    const ownerCell=document.createElement('td');
    ownerCell.innerHTML=`<code>${owner}</code>`;
    const apiCell=document.createElement('td');
    apiCell.innerHTML=`<code>${api}</code>`;
    const useCell=document.createElement('td');
    useCell.textContent=use;
    tr.append(ownerCell,apiCell,useCell);
    body.appendChild(tr);
  });
  table.appendChild(body);
  scroll.appendChild(table);
  wrap.appendChild(scroll);
  const bottomNav=[...article.querySelectorAll('.page-nav')].at(-1);
  if(bottomNav) article.insertBefore(wrap,bottomNav); else article.appendChild(wrap);
}
addApiReference();

// Add a compact copy button to every tutorial code block.
document.querySelectorAll('pre').forEach(pre=>{
  if(pre.querySelector('.code-copy-btn')) return;
  const button=document.createElement('button');
  button.type='button';
  button.className='code-copy-btn';
  button.setAttribute('aria-label','Copy code');
  button.setAttribute('title','Copy');
  button.textContent='Copy';
  button.addEventListener('click',async()=>{
    const code=pre.querySelector('code');
    const text=code?code.textContent:pre.textContent;
    try{
      await navigator.clipboard.writeText(text);
      button.textContent='Copied';
      button.classList.add('copied');
      setTimeout(()=>{button.textContent='Copy';button.classList.remove('copied');},1200);
    }catch{
      const area=document.createElement('textarea');
      area.value=text;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();button.textContent='Copied';button.classList.add('copied');setTimeout(()=>{button.textContent='Copy';button.classList.remove('copied');},1200);
    }
  });
  pre.appendChild(button);
});

// Operating-system tabs. The student's choice is remembered across every tutorial page.
const osKey='prefab-docs-os';
let selectedOS=localStorage.getItem(osKey);
if(!['windows','linux'].includes(selectedOS)) selectedOS='windows';
const osGrids=[...document.querySelectorAll('.os-grid')];
osGrids.forEach(grid=>{
  const sections=[...grid.querySelectorAll(':scope > section')];
  sections.forEach((panel,index)=>{
    const label=(panel.querySelector('.os-label')?.textContent||'').toLowerCase();
    panel.dataset.os=label.includes('linux')?'linux':label.includes('windows')?'windows':index===0?'windows':'linux';
  });
  const tabs=document.createElement('div');
  tabs.className='os-tabs';tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Choose your operating system');tabs.innerHTML='<button type="button" data-os-choice="windows">Windows</button><button type="button" data-os-choice="linux">Linux</button>';grid.before(tabs);
});
function applyOS(os){selectedOS=os;localStorage.setItem(osKey,os);document.documentElement.dataset.os=os;document.querySelectorAll('.os-tabs button').forEach(button=>{const active=button.dataset.osChoice===os;button.classList.toggle('active',active);button.setAttribute('aria-selected',active?'true':'false');});document.querySelectorAll('.os-grid > section').forEach(panel=>{panel.hidden=panel.dataset.os!==os;});}
document.querySelectorAll('.os-tabs button').forEach(button=>button.addEventListener('click',()=>applyOS(button.dataset.osChoice)));
if(osGrids.length) applyOS(selectedOS);

