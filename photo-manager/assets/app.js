'use strict';
const $=id=>document.getElementById(id);
const cards=[...document.querySelectorAll('.card')].map(el=>({el,id:el.dataset.id,jpeg:+el.dataset.jpeg,webp:+el.dataset.webp,ai:+el.dataset.ai,pdf:+el.dataset.pdf,bytes:+el.dataset.bytes}));
const selection=new LevSelection.Selection(cards);
let running=false,paused=false;
function render() {
  const t=selection.totals();
  $('summary').textContent=`Выбрано: ${t.photos} · JPEG: ${t.jpeg} · ${(t.bytes/1048576).toFixed(2)} МиБ`;
  $('eligible').textContent=`Подходят: ${cards.filter(c=>LevSelection.matches(c,$('select-mode').value)).length}`;
  cards.forEach(c=>c.el.querySelector('input').checked=selection.selected.has(c.id));
  $('convert').disabled=running || !t.photos;
}
$('gallery').addEventListener('change',e=>{if(e.target.matches('.photo-check')) { selection.set(e.target.value,e.target.checked); render(); }});
$('select-all').addEventListener('click',()=>{selection.selectVisible($('select-mode').value);render();});
$('clear').addEventListener('click',()=>{selection.clear();render();});
$('select-mode').addEventListener('change',render);
async function api(action,extra={}) {
  const body=new FormData();body.set('csrf',$('gallery').dataset.csrf);body.set('action',action);
  Object.entries(extra).forEach(([k,v])=>Array.isArray(v)?v.forEach(id=>body.append(k+'[]',id)):body.set(k,v));
  const res=await fetch('api.php',{method:'POST',body,credentials:'same-origin'});
  const result=await res.json();if(!res.ok || !result.ok) throw Error(result.error || `HTTP ${res.status}`);
  return result.data;
}
function report(job) {
  const counts={pending:0,processing:0,converted:0,skipped:0,failed:0};job.items.forEach(i=>counts[i.status]++);
  $('progress').max=job.items.length || 1;$('progress').value=counts.converted+counts.skipped+counts.failed;
  $('job-status').textContent=`Ожидают: ${counts.pending} · Обрабатываются: ${counts.processing} · Готово: ${counts.converted} · Пропущено: ${counts.skipped} · Ошибок: ${counts.failed}`;
  $('report').textContent=job.items.filter(i=>i.message).map(i=>`${i.source || i.group}: ${i.message}`).join('\n');
  return counts.pending+counts.processing;
}
async function run(id) {
  running=true;paused=false;$('resume').hidden=true;$('pause').hidden=false;render();
  try {
    let job=await api('status',{job:id});
    while(report(job) && !paused) job=await api('step',{job:id});
    if(!report(job)) {sessionStorage.removeItem('levpro-job');$('job-status').append(' · Обработка завершена. Обновите страницу для новых индикаторов.');}
    else $('resume').hidden=false;
  } catch(e) {$('job-status').textContent=`Обработка остановлена: ${e.message}. Можно продолжить.`;$('resume').hidden=false;}
  finally {running=false;$('pause').hidden=true;render();}
}
$('convert').addEventListener('click',async()=>{
  if(running || sessionStorage.getItem('levpro-job')) { $('job-status').textContent='Сначала продолжите текущее задание.';return; }
  try {
    const extra={selected:[...selection.selected],format:$('format').value,width:$('width').value,quality:$('quality').value};
    if($('exclude').checked) extra.exclude_documents='1';
    running=true;render();const job=await api('create',extra);sessionStorage.setItem('levpro-job',job.job);await run(job.job);
  } catch(e) {$('job-status').textContent=e.message;} finally {running=false;render();}
});
$('resume').addEventListener('click',()=>{const id=sessionStorage.getItem('levpro-job');if(id&&!running)run(id);});
$('pause').addEventListener('click',()=>{paused=true;});
if(sessionStorage.getItem('levpro-job')) $('resume').hidden=false;
render();
