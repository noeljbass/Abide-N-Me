import { api } from './api.js';
import { openExternal } from './native.js';

export function initPlans() {
  const form=document.querySelector('[data-plan-form]'); if(!form)return;
  const list=document.querySelector('[data-plans-list]'),empty=document.querySelector('[data-plans-empty]'),message=document.querySelector('[data-plan-message]');
  const groups=form.querySelector('[data-plan-group]'),books=form.querySelector('[data-plan-books]'),manual=form.querySelector('[data-manual-days]');let user=null,dayCount=0;
  const jsonInput=form.querySelector('[data-plan-json]');
  const jsonPreview=form.querySelector('[data-json-preview]');
  const jsonMessage=form.querySelector('[data-json-message]');
  const modeSections={automatic:form.querySelector('[data-automatic-fields]'),manual:form.querySelector('[data-manual-fields]'),json:form.querySelector('[data-json-fields]')};

  const weekdays=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];form.querySelector('[data-weekdays]').innerHTML=weekdays.map((name,i)=>`<label><input type="checkbox" value="${i+1}" checked> ${name}</label>`).join('');
  form.elements.start_date.value=new Date().toISOString().slice(0,10);form.querySelector('[data-plan-timezone]').value=Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC';
  function show(value,success=false){message.textContent=value;message.hidden=!value;message.classList.toggle('is-success',success);}
  function addDay(){dayCount++;const card=document.createElement('fieldset');card.className='manual-day';card.innerHTML=`<legend>Day ${dayCount}</legend><label>Title <small>Optional</small><input data-day-title maxlength="150"></label><label>Passages <small>One per line</small><textarea data-day-passages required placeholder="John 1:1-18&#10;Psalm 23"></textarea></label><label>Day note <small>Optional</small><textarea data-day-note maxlength="5000"></textarea></label><label>Discussion question <small>Optional</small><textarea data-day-question maxlength="5000"></textarea></label>`;manual.append(card);}
  addDay();
  async function load(){if(!user){list.replaceChildren();empty.hidden=false;groups.innerHTML='<option value="">Sign in to choose a group</option>';return;}try{const [planData,groupData,bookData]=await Promise.all([api('plans/index.php'),api('groups/index.php'),api('bible/books.php?translation=DRA1899')]);list.replaceChildren(...planData.plans.map(card));empty.hidden=planData.plans.length>0;const allowed=groupData.groups.filter(g=>g.role==='owner'||g.role==='admin');groups.innerHTML=allowed.length?allowed.map(g=>`<option value="${g.id}">${escapeText(g.name)}</option>`).join(''):'<option value="">Create or administer a group first</option>';books.innerHTML=bookData.books.map(b=>`<label><input type="checkbox" value="${b.code}"> <span>${escapeText(b.name)}</span></label>`).join('');}catch(error){show(error.message);}}
  function card(plan){const article=document.createElement('article');article.className='compact-card plan-card';const body=document.createElement('div');const kicker=document.createElement('span');kicker.className='card-kicker';kicker.textContent=`${plan.group_name} · ${plan.builder_mode}`;const title=document.createElement('h2');title.textContent=plan.name;const meta=document.createElement('p');meta.textContent=`${plan.day_count} reading ${plan.day_count===1?'day':'days'} · starts ${plan.start_date}`;body.append(kicker,title,meta);const state=document.createElement('span');state.className='plan-status';state.textContent=plan.status;article.append(body,state);return article;}
  function escapeText(value){const span=document.createElement('span');span.textContent=value;return span.innerHTML;}
  function syncMode(){
    const mode=new FormData(form).get('mode');
    // Only the selected mode's controls stay enabled: a disabled field is skipped
    // by form validation, so a hidden required textarea cannot block submission.
    for(const [name,section] of Object.entries(modeSections)){
      if(!section)continue;
      const active=name===mode;
      section.hidden=!active;
      section.querySelectorAll('input, select, textarea, button').forEach(control=>{control.disabled=!active;});
    }
  }
  syncMode();
  form.addEventListener('change',event=>{if(event.target.name==='mode')syncMode();});
  form.querySelector('[data-add-plan-day]').addEventListener('click',addDay);
  form.addEventListener('submit',async event=>{
    event.preventDefault();show('');
    const mode=new FormData(form).get('mode');
    const submit=form.querySelector('[type="submit"]');
    // A pasted plan is a manual plan; the JSON is only a faster way to describe one.
    let source=null;
    if(mode==='json'){
      const parsed=parseJsonPlan(jsonInput.value);
      if(parsed.error){show(parsed.error);renderPreview(null,parsed.error);return;}
      source=parsed.plan;
    }
    const payload={
      group_id:groups.value,
      name:source?.name||form.elements.name.value,
      description:source?.description??form.elements.description.value,
      mode:mode==='json'?'manual':mode,
      start_date:source?.start_date||form.elements.start_date.value,
      timezone:form.elements.timezone.value,
      translation:source?.translation||'DRA1899',
    };
    if(mode==='automatic'){
      payload.books=[...books.querySelectorAll('input:checked')].map(input=>input.value);
      payload.duration_days=Number(form.elements.duration_days.value);
      payload.weekdays=[...form.querySelectorAll('[data-weekdays] input:checked')].map(input=>Number(input.value));
    }else if(mode==='json'){
      payload.days=source.days.map(day=>({title:day.title,passages:day.passages.join('\n'),note:day.note,question:day.question}));
    }else{
      payload.days=[...manual.querySelectorAll('.manual-day')].map(day=>({title:day.querySelector('[data-day-title]').value,passages:day.querySelector('[data-day-passages]').value,note:day.querySelector('[data-day-note]').value,question:day.querySelector('[data-day-question]').value}));
    }
    submit.disabled=true;
    try{
      await api('plans/index.php',{method:'POST',body:payload});
      show('Plan created and assigned to your group.',true);
      form.reset();form.elements.start_date.value=new Date().toISOString().slice(0,10);
      manual.replaceChildren();dayCount=0;addDay();
      jsonInput.value='';renderPreview(null);showJson('');
      syncMode();
      document.querySelector('[data-plan-builder]').open=false;
      await load();
    }catch(error){show(error.message);}finally{submit.disabled=false;}
  });
  // ------------------------------------------------------------ JSON plans ----
  // A plan pasted as JSON is turned into the same payload the manual builder
  // sends. Everything here is validation for the sake of a readable message: the
  // server checks all of it again, and only the server can tell whether a
  // reference actually exists in the chosen translation.

  function showJson(value,success=false){jsonMessage.textContent=value;jsonMessage.hidden=!value;jsonMessage.classList.toggle('is-success',success);}

  const text=(value,limit,label,index)=>{
    if(value===undefined||value===null||value==='')return null;
    if(typeof value!=='string')throw new Error(`${label}${index!==undefined?` on day ${index+1}`:''} must be text.`);
    const trimmed=value.trim();
    if(trimmed.length>limit)throw new Error(`${label}${index!==undefined?` on day ${index+1}`:''} is longer than ${limit} characters.`);
    return trimmed||null;
  };

  function parseJsonPlan(raw){
    if(!raw||!raw.trim())return {error:'Paste a plan in JSON first.'};
    let data;
    try{data=JSON.parse(raw);}
    catch(error){return {error:`That is not valid JSON. ${error.message}`};}
    if(!data||typeof data!=='object'||Array.isArray(data))return {error:'The JSON must be a single object describing one plan.'};

    try{
      const name=text(data.name,150,'Plan name');
      if(!name||name.length<2)return {error:'The plan needs a name of at least 2 characters.'};
      if(!Array.isArray(data.days)||!data.days.length)return {error:'The plan needs a "days" array with at least one day.'};
      if(data.days.length>730)return {error:`A plan can hold at most 730 days; this one has ${data.days.length}.`};

      const startDate=text(data.start_date,10,'Start date');
      if(startDate&&!/^\d{4}-\d{2}-\d{2}$/.test(startDate))return {error:'Start date must be written as YYYY-MM-DD.'};

      const days=data.days.map((day,index)=>{
        if(!day||typeof day!=='object'||Array.isArray(day))throw new Error(`Day ${index+1} must be an object.`);
        // A single passage may be written as one string rather than a list.
        let passages=day.passages;
        if(typeof passages==='string')passages=passages.split(/\r?\n/);
        if(!Array.isArray(passages))throw new Error(`Day ${index+1} needs a "passages" list.`);
        passages=passages.map(value=>String(value??'').trim()).filter(Boolean);
        if(!passages.length)throw new Error(`Day ${index+1} has no passages.`);
        if(passages.length>20)throw new Error(`Day ${index+1} has ${passages.length} passages; the limit is 20.`);
        return {
          title:text(day.title,150,'Title',index),
          passages,
          note:text(day.note,5000,'Note',index),
          question:text(day.question,5000,'Discussion question',index),
        };
      });

      return {plan:{name,description:text(data.description,5000,'Description'),translation:text(data.translation,20,'Translation'),start_date:startDate,days}};
    }catch(error){
      return {error:error.message};
    }
  }

  /** The leading book name of a reference, for the summary of books covered. */
  function bookOf(reference){
    const match=reference.match(/^\s*((?:[1-3]\s*)?[A-Za-z][A-Za-z.'\u2019\- ]*?)\s+\d/);
    return (match?match[1]:reference).replace(/\s+/g,' ').trim();
  }

  function dayDate(startDate,offset){
    if(!startDate)return null;
    const date=new Date(`${startDate}T00:00:00`);
    if(Number.isNaN(date.getTime()))return null;
    date.setDate(date.getDate()+offset);
    return date.toISOString().slice(0,10);
  }

  function renderPreview(plan,error){
    jsonPreview.replaceChildren();
    jsonPreview.hidden=!plan;
    if(!plan)return;

    const startDate=plan.start_date||form.elements.start_date.value;
    const add=(parent,tag,className,value)=>{const node=document.createElement(tag);if(className)node.className=className;if(value!==undefined&&value!==null)node.textContent=value;parent.append(node);return node;};

    const head=add(jsonPreview,'div','json-preview-head');
    add(head,'p','eyebrow','Preview');
    add(head,'h3',null,plan.name);
    if(plan.description)add(head,'p','muted',plan.description);

    const books=[...new Set(plan.days.flatMap(day=>day.passages.map(bookOf)))];
    const facts=add(head,'dl','json-facts');
    const fact=(term,value)=>{add(facts,'dt',null,term);add(facts,'dd',null,value);};
    fact('Reading days',String(plan.days.length));
    fact('Runs',startDate?`${startDate} to ${dayDate(startDate,plan.days.length-1)}`:'once you choose a start date');
    fact('Translation',plan.translation||'DRA1899 (default)');
    fact(books.length===1?'Book':'Books',books.join(', '));
    fact('Passages',String(plan.days.reduce((total,day)=>total+day.passages.length,0)));

    const list=add(jsonPreview,'ol','json-days');
    plan.days.forEach((day,index)=>{
      const item=add(list,'li','json-day');
      const heading=add(item,'div','json-day-head');
      add(heading,'span','json-day-number',`Day ${index+1}`);
      const date=dayDate(startDate,index);
      if(date)add(heading,'span','json-day-date',date);
      if(day.title)add(item,'h4',null,day.title);
      const refs=add(item,'div','json-passages');
      day.passages.forEach(reference=>add(refs,'span','json-passage',reference));
      if(day.note)add(item,'p','json-day-note',day.note);
      if(day.question)add(item,'blockquote',null,day.question);
    });
  }

  const PLAN_GENERATOR='https://chatgpt.com/g/g-6815270daed881918b39df44232d26ad-askfaithbot-abide-n-me-bible-plan-generator';
  form.querySelector('[data-json-generate]').addEventListener('click',()=>{openExternal(PLAN_GENERATOR);});

  form.querySelector('[data-json-preview-button]').addEventListener('click',()=>{
    const parsed=parseJsonPlan(jsonInput.value);
    if(parsed.error){showJson(parsed.error);renderPreview(null);return;}
    // Anything the plan states about itself is copied into the form, so the
    // values that will actually be submitted are visible and still editable.
    if(parsed.plan.name)form.elements.name.value=parsed.plan.name;
    if(parsed.plan.description)form.elements.description.value=parsed.plan.description;
    if(parsed.plan.start_date)form.elements.start_date.value=parsed.plan.start_date;
    showJson(`${parsed.plan.days.length} reading ${parsed.plan.days.length===1?'day':'days'} read successfully. Check the preview, then create the plan.`,true);
    renderPreview(parsed.plan);
  });

  window.addEventListener('auth:changed',event=>{user=event.detail.user;load();});
  window.addEventListener('route:changed',event=>{if(event.detail.route==='plans')load();});
}
