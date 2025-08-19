
const API_BASE = new URL('./api/', location.href);
const RESULT_CODE = (crypto.getRandomValues(new Uint32Array(1))[0].toString(36).slice(-8)).toUpperCase();
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('copyresultid');
  if (el) { if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) el.firstChild.nodeValue = RESULT_CODE; else el.textContent = RESULT_CODE; }
});
fetch(new URL('session.php', API_BASE), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({result_code:RESULT_CODE, action:'start'})}).catch(()=>{});
if (!window.__ANSWER_BRIDGE_INSTALLED__) {
  window.__ANSWER_BRIDGE_INSTALLED__ = true;
  const sent = new Set(); const windowMs = 1500; const makeKey = (p)=>[p.textId,p.gapId,p.topic,p.choice,p.correct].join('|');
  async function sendAnswer(payload){
    const key = makeKey(payload||{}); if (sent.has(key)) return; sent.add(key); setTimeout(()=>sent.delete(key), windowMs);
    try{ await fetch(new URL('answer.php', API_BASE), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ result_code: RESULT_CODE, ...payload }) }); }catch(e){}
  }
  document.addEventListener('gapAnswer', ev => { if (ev?.detail) sendAnswer(ev.detail); });
  if (!window.onGapAnswer) window.onGapAnswer = (payload)=>sendAnswer(payload);
}