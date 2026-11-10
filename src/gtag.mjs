// Znaczniki Google z trybem zgód. Skrypt Google ładuje się dopiero po zgodzie z banera cookies
// (zdarzenie cv-consent, zapis w localStorage cv-consent-v1):
//  - zgoda analityczna  -> Google Analytics 4 (G-...)
//  - zgoda marketingowa -> Google Ads (AW-...)
// Udane wysłanie formularza kontaktowego (POST do formsubmit.co) zgłasza konwersję Ads i zdarzenie generate_lead w GA.
const AW = 'AW-18567029172';
const LEAD = AW + '/7CwaCPqD1bUdELTDuZVF';
const GA = 'G-J24C6Z264G';
export const GTAG_HTML = `<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}` +
  `gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});` +
  `(function(){var lib=0,ga=0,aw=0;function load(id){if(lib)return;lib=1;var j=document.createElement('script');j.async=true;` +
  `j.src='https://www.googletagmanager.com/gtag/js?id='+id;document.head.appendChild(j);gtag('js',new Date())}` +
  `function apply(c){if(!c)return;var m=c.marketing?'granted':'denied';` +
  `gtag('consent','update',{analytics_storage:c.analytics?'granted':'denied',ad_storage:m,ad_user_data:m,ad_personalization:m});` +
  `if(c.analytics&&!ga){load('${GA}');ga=1;gtag('config','${GA}')}` +
  `if(c.marketing&&!aw){load('${AW}');aw=1;gtag('config','${AW}')}}` +
  `try{apply(JSON.parse(localStorage.getItem('cv-consent-v1')||'null'))}catch(e){}` +
  `window.addEventListener('cv-consent',function(e){apply(e.detail)});` +
  `var f=window.fetch;if(f)window.fetch=function(u,o){var p=f.apply(this,arguments);` +
  `try{if(o&&o.method==='POST'&&String(u).indexOf('formsubmit.co')>-1)p.then(function(r){if(r&&r.ok){` +
  `if(aw)gtag('event','conversion',{send_to:'${LEAD}',value:1.0,currency:'PLN'});` +
  `if(ga)gtag('event','generate_lead',{form_name:'kontakt'})}})}catch(e){}` +
  `return p}})();</script>`;
