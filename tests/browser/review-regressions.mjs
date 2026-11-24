// Run after PHPUnit has prepared pasa_buy_test, with review-router.php on :8087.
// Node 22+, local Chrome; no npm dependencies. Only synthetic test data.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import assert from 'node:assert/strict';
const base = 'http://127.0.0.1:8087/';
const profile = mkdtempSync(join(tmpdir(), 'pb-review-'));
const proc = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--remote-debugging-port=9337',`--user-data-dir=${profile}`,'--no-first-run','--disable-extensions','about:blank'],{stdio:'ignore',windowsHide:true});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
let ws, serial=0; const pending=new Map();
const fixture=action=>execFileSync('php',[resolve('tests/browser/review-fixture.php'),action],{stdio:'pipe',windowsHide:true});
const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial; const timer=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout: '+method));},15000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(new Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result?.value;};
async function until(expr){for(let i=0;i<100;i++){if(await evaluate(expr))return;await delay(100);}throw Error('Timed out: '+expr);}
async function go(path){await send('Page.navigate',{url:base+path});await until(`location.href===${JSON.stringify(base+path)} && document.readyState==='complete'`);}
async function login(){await go('login');await evaluate(`document.querySelector('#email').value='review-browser@example.test';document.querySelector('#password').value='ReviewPass#2026';document.querySelector('form[data-auth-form]').requestSubmit()`);await until(`location.pathname==='/dashboard' && document.readyState==='complete'`);}
try {
 for(let i=0;i<50;i++){try{const pages=await(await fetch('http://127.0.0.1:9337/json/list')).json();const page=pages.find(p=>p.type==='page');if(page){ws=new WebSocket(page.webSocketDebuggerUrl);break;}}catch{}await delay(100);}
 if(!ws)throw Error('Chrome did not start');
 await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}});
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
 fixture('remove');fixture('create');
 await go('register');await until(`!!window.Turbo && !!document.querySelector('form[data-auth-form]')?.pasabuyAuthReady`);
 await evaluate(`document.querySelector('#first_name').value='Browser';document.querySelector('#last_name').value='Review';document.querySelector('#phone').value='09999990001';document.querySelector('#address').value='Synthetic address';document.querySelector('#email').value='review-browser@example.test';document.querySelector('[data-step-next]').click()`);
 assert.equal(await evaluate(`document.querySelector('[data-step="2"]').hidden`),false);
 await evaluate(`window.reviewDocumentMarker=1;Turbo.visit('/about')`);await until(`location.pathname==='/about' && !!document.querySelector('.site-footer')`);
 await evaluate('history.back()');await until(`location.pathname==='/register' && !!document.querySelector('form[data-auth-form]')?.pasabuyAuthReady`);
 assert.equal(await evaluate('window.reviewDocumentMarker'),1,'must restore within Turbo document');
 await evaluate(`document.querySelector('[data-step-back]').click();document.querySelector('[data-step-next]').click()`);
 assert.equal(await evaluate(`document.querySelector('[data-step="2"]').hidden`),false);
 console.log('PASS sign-up Continue after Turbo Back');
 await evaluate(`document.body.insertAdjacentHTML('beforeend','<form id="reviewSubmit" data-submit-once><button type="submit" data-busy-label="Saving">Save</button><button type="submit" disabled>Unavailable</button></form>');document.addEventListener('submit',e=>{if(e.target.id==='reviewSubmit')e.preventDefault()});document.querySelector('#reviewSubmit').requestSubmit()`);
 assert.equal(await evaluate(`document.querySelector('#reviewSubmit button').disabled`),true);
 await evaluate(`Turbo.visit('/about')`);await until(`location.pathname==='/about'`);await delay(300);await evaluate('history.back()');
 await until(`!!document.querySelector('#reviewSubmit')`);
 assert.deepEqual(await evaluate(`Array.from(document.querySelectorAll('#reviewSubmit button')).map(b=>[b.disabled,b.textContent,b.hasAttribute('aria-busy')])`),[[false,'Save',false],[true,'Unavailable',false]]);
 console.log('PASS submit-once Turbo Back resets label/lock and preserves pre-disabled controls');
 await login();
 await evaluate(`(()=>{const f=document.createElement('form');f.method='post';f.action='/logout';f.dataset.turbo='false';const t=document.createElement('input');t.name=document.querySelector('meta[name="csrf-token-name"]').content;t.value=document.querySelector('meta[name="csrf-token"]').content;f.append(t);document.body.append(f);f.submit()})()`);
 await until(`location.pathname==='/login' && document.readyState==='complete'`);
 assert.ok(await evaluate(`document.body.textContent.includes('Logged out successfully.')`));
 await send('Page.navigate',{url:base+'dashboard'});await until(`location.pathname==='/login' && document.readyState==='complete'`);
 console.log('PASS logout flash survives real redirect and authenticated access ends');
 await login();fixture('suspend');await send('Page.navigate',{url:base+'profile'});
 await until(`location.pathname==='/login' && document.readyState==='complete'`);
 assert.ok(await evaluate(`document.body.textContent.includes('This account is suspended.')`));
 console.log('PASS suspension flash survives real redirect');
 console.log('4 browser regressions passed');
} finally {try{fixture('remove');}catch{}if(ws)ws.close();proc.kill();}
