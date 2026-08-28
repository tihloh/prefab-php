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
      setTimeout(()=>{
        button.textContent='Copy';
        button.classList.remove('copied');
      },1200);
    }catch{
      const area=document.createElement('textarea');
      area.value=text;
      area.style.position='fixed';
      area.style.opacity='0';
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      area.remove();
      button.textContent='Copied';
      button.classList.add('copied');
      setTimeout(()=>{
        button.textContent='Copy';
        button.classList.remove('copied');
      },1200);
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
  tabs.className='os-tabs';
  tabs.setAttribute('role','tablist');
  tabs.setAttribute('aria-label','Choose your operating system');
  tabs.innerHTML='<button type="button" data-os-choice="windows">Windows</button><button type="button" data-os-choice="linux">Linux</button>';
  grid.before(tabs);
});
function applyOS(os){
  selectedOS=os;
  localStorage.setItem(osKey,os);
  document.documentElement.dataset.os=os;
  document.querySelectorAll('.os-tabs button').forEach(button=>{
    const active=button.dataset.osChoice===os;
    button.classList.toggle('active',active);
    button.setAttribute('aria-selected',active?'true':'false');
  });
  document.querySelectorAll('.os-grid > section').forEach(panel=>{
    panel.hidden=panel.dataset.os!==os;
  });
}
document.querySelectorAll('.os-tabs button').forEach(button=>button.addEventListener('click',()=>applyOS(button.dataset.osChoice)));
if(osGrids.length) applyOS(selectedOS);
