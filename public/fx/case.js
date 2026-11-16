(function(){
var R=matchMedia('(prefers-reduced-motion: reduce)').matches,d=document,root=d.querySelector('.gg3');if(!root)return;
var rv=root.querySelectorAll('.gg3-2c,.gg3-nums,.gg3-find li,.gg3-proc li,.gg3-mob figure,.gg3-cta,.gg3-shots,.gg3-goals li,.gg3-zig article,.gg3-stack article,.gg3-got li,.gg3-note div');
if(!R&&'IntersectionObserver'in window){d.documentElement.classList.add('gg3js');var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});rv.forEach(function(el,i){el.classList.add('gg3-rv');el.style.transitionDelay=(el.matches('li,figure')?((Array.prototype.indexOf.call(el.parentNode.children,el)%5)*80):0)+'ms';io.observe(el)})}
var arts=root.querySelectorAll('.gg3-sticky article');
function onCh(){var mid=innerHeight*.45,cur=arts[0];arts.forEach(function(a){if(a.getBoundingClientRect().top<mid)cur=a});arts.forEach(function(a){a.classList.toggle('is-on',a===cur)})}
var proc=root.querySelector('.gg3-proc'),bar=root.querySelector('.gg3-axis i');
function onPr(){if(!proc||!bar)return;var r=proc.getBoundingClientRect(),p=(innerHeight*.85-r.top)/(innerHeight*.6);p=Math.max(0,Math.min(1,p));bar.style.setProperty('--p',(R?100:p*100)+'%')}
var cnt=root.querySelectorAll('[data-count]');
if(!R&&'IntersectionObserver'in window){var co=new IntersectionObserver(function(es){es.forEach(function(e){if(!e.isIntersecting)return;co.unobserve(e.target);var el=e.target,n=+el.getAttribute('data-count'),t0=performance.now();(function f(t){var k=Math.min(1,(t-t0)/1200);k=1-Math.pow(1-k,3);el.textContent=Math.round(n*k);if(k<1)requestAnimationFrame(f)})(t0)})},{threshold:.6});cnt.forEach(function(el){co.observe(el)})}
var tk=0;function tick(){tk=0;onCh();onPr()}
addEventListener('scroll',function(){if(!tk){tk=1;requestAnimationFrame(tick)}},{passive:true});addEventListener('resize',tick);tick();
})();
