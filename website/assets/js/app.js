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
