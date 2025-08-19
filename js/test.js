
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


// (tg bot code removed)

// === Share result to Telegram (no bot) ===
(function(){
  function buildText() {
    // Try to extract simple summary if present in DOM; fallback to result code only.
    const head = 'Мой результат на orfo.club';
    const code = RESULT_CODE || '';
    // If your app renders summary element with id 'result' — include it
    let summary = '';
    const el = document.getElementById('result') || document.getElementById('results') || document.getElementById('final');
    if (el) summary = el.innerText.trim().slice(0, 500);
    return [head, summary, 'Код попытки: ' + code].filter(Boolean).join('\\n');
  }
  function makeBtn(){
    const a = document.createElement('a');
    a.id = 'tg-share-btn';
    a.textContent = 'Отправить результат в Telegram';
    const text = encodeURIComponent(buildText());
    const url  = encodeURIComponent(location.href);
    a.href = `https://t.me/share/url?url=${url}&text=${text}`;
    a.target = '_blank'; a.rel='noopener';
    a.style.display = 'inline-block'; a.style.marginTop = '12px';
    a.style.padding = '10px 14px'; a.style.border = '1px solid #229ED9';
    a.style.borderRadius = '6px'; a.style.textDecoration = 'none'; a.style.fontWeight = '600';
    a.style.fontFamily = 'system-ui, -apple-system, Segoe UI, Roboto, Arial';
    return a;
  }
  function appendBtn(){
    if (document.getElementById('tg-share-btn')) return;
    const btn = makeBtn();
    const container = document.getElementById('result') || document.getElementById('results') || document.getElementById('final') || document.body;
    container.appendChild(btn);
  }
  if (typeof window.showResults === 'function'){
    const _orig = window.showResults;
    window.showResults = function(){
      try { return _orig.apply(this, arguments); }
      finally { setTimeout(appendBtn, 0); }
    };
  } else {
    document.addEventListener('DOMContentLoaded', ()=> setTimeout(appendBtn, 1500));
  }
})();
