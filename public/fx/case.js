(function(){
var R=matchMedia('(prefers-reduced-motion: reduce)').matches,d=document,root=d.querySelector('.gg3');if(!root)return;
var rv=d.querySelectorAll('.gg3-2c,.gg3-nums,.gg3-find li,.gg3-proc li,.gg3-mob figure,.gg3-cta,.gg3-shots,.gg3-goals li,.gg3-zig article,.gg3-stack article,.gg3-got li,.gg3-note div');
if(!R&&'IntersectionObserver'in window){d.documentElement.classList.add('gg3js');var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});rv.forEach(function(el,i){el.classList.add('gg3-rv');el.style.transitionDelay=(el.matches('li,figure')?((Array.prototype.indexOf.call(el.parentNode.children,el)%5)*80):0)+'ms';io.observe(el)})}
var arts=d.querySelectorAll('.gg3-sticky article');
function onCh(){var mid=innerHeight*.45,cur=arts[0];arts.forEach(function(a){if(a.getBoundingClientRect().top<mid)cur=a});arts.forEach(function(a){a.classList.toggle('is-on',a===cur)})}
var proc=d.querySelector('.gg3-proc'),bar=d.querySelector('.gg3-axis i');
var fds=d.querySelectorAll('.gg3-find');function onFd(){fds.forEach(function(el){var r=el.getBoundingClientRect(),p=(innerHeight*.85-r.top)/(innerHeight*.6);p=Math.max(.12,Math.min(1,p));el.style.setProperty('--p',(R?100:p*100)+'%')})}
function onPr(){if(!proc||!bar)return;var r=proc.getBoundingClientRect(),p=(innerHeight*.85-r.top)/(innerHeight*.6);p=Math.max(0,Math.min(1,p));bar.style.setProperty('--p',(R?100:p*100)+'%')}
var cnt=d.querySelectorAll('[data-count]');
if(!R&&'IntersectionObserver'in window){var co=new IntersectionObserver(function(es){es.forEach(function(e){if(!e.isIntersecting)return;co.unobserve(e.target);var el=e.target,n=+el.getAttribute('data-count'),t0=performance.now();(function f(t){var k=Math.min(1,(t-t0)/1200);k=1-Math.pow(1-k,3);el.textContent=Math.round(n*k);if(k<1)requestAnimationFrame(f)})(t0)})},{threshold:.6});cnt.forEach(function(el){co.observe(el)})}
var tk=0;function tick(){tk=0;onCh();onPr();onFd()}
addEventListener('scroll',function(){if(!tk){tk=1;requestAnimationFrame(tick)}},{passive:true});addEventListener('resize',tick);tick();
})();
(function(){if(matchMedia('(hover:none)').matches||matchMedia('(prefers-reduced-motion: reduce)').matches)return;document.querySelectorAll('.gg3-tilt').forEach(function(f){var s=f.closest('section')||f;s.addEventListener('pointermove',function(e){var b=f.getBoundingClientRect(),x=(e.clientX-(b.left+b.width/2))/b.width,y=(e.clientY-(b.top+b.height/2))/b.height;f.style.setProperty('--ry',(x*9).toFixed(2)+'deg');f.style.setProperty('--rx',(-y*7).toFixed(2)+'deg')});s.addEventListener('pointerleave',function(){f.style.removeProperty('--ry');f.style.removeProperty('--rx')})})})();
(function(){var d=document;if(!d.querySelector('.gg3'))return;
function add(){if(d.querySelector('.gg-live'))return;var l=d.querySelector('.cv-ih .lead');if(!l)return;
var a=d.createElement('a');a.className='gg-live';a.href='https://upbycwwllq.cfolks.pl/';a.target='_blank';a.rel='noopener nofollow';
a.innerHTML='Zobacz nowy sklep na żywo<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>';
var n=d.createElement('p');n.className='gg-live-n';n.textContent='Wersja przed startem, na serwerze testowym. Wkrótce pod adresem goldengun.pl.';
l.after(a,n)}
add();addEventListener('load',add);setTimeout(add,1500);setTimeout(add,4000);
new MutationObserver(add).observe(d.body,{childList:true,subtree:true});})();

