// Znaczniki Google w zaawansowanym trybie zgód (Consent Mode v2, advanced).
// Biblioteka gtag ładuje się od razu, ale wszystkie zgody startują jako "denied":
// bez zgody Google dostaje tylko anonimowe pingi bez cookies, z których modeluje konwersje.
// Decyzja z banera (zdarzenie cv-consent, localStorage cv-consent-v1) aktualizuje zgody:
//  - zgoda analityczna  -> analytics_storage
//  - zgoda marketingowa -> ad_storage, ad_user_data, ad_personalization
// Konwersje:
//  - window.cvLead() woła formularz po udanym wysłaniu (make.com albo formsubmit.co)
//  - kliknięcie w link tel: lub mailto: zgłasza konwersję "Kliknięcie w telefon lub e-mail"
// Źródło wejścia (gclid, utm_*, pierwsza strona) trafia do sessionStorage cv-src i do treści zapytania.
const AW = 'AW-18567029172';
const LEAD = AW + '/7CwaCPqD1bUdELTDuZVF';
const CALL = AW + '/S1ZrCI7Zg7cdELTDuZVF';
const GA = 'G-J24C6Z264G';
export const GTAG_HTML = `<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}` +
  `gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});` +
  `gtag('set','url_passthrough',true);gtag('set','ads_data_redaction',true);` +
  `(function(){var j=document.createElement('script');j.async=true;j.src='https://www.googletagmanager.com/gtag/js?id=${GA}';document.head.appendChild(j);` +
  `gtag('js',new Date());gtag('config','${GA}');gtag('config','${AW}');` +
  `function apply(c){if(!c)return;var m=c.marketing?'granted':'denied';` +
  `gtag('consent','update',{analytics_storage:c.analytics?'granted':'denied',ad_storage:m,ad_user_data:m,ad_personalization:m});` +
  `if(c.marketing)gtag('set','ads_data_redaction',false)}` +
  `try{apply(JSON.parse(localStorage.getItem('cv-consent-v1')||'null'))}catch(e){}` +
  `window.addEventListener('cv-consent',function(e){apply(e.detail)});` +
  `try{if(!sessionStorage.getItem('cv-src')){var q=new URLSearchParams(location.search),s=[];` +
  `['gclid','utm_source','utm_medium','utm_campaign','utm_term'].forEach(function(k){var v=q.get(k);if(v)s.push(k+'='+v.slice(0,80))});` +
  `if(document.referrer&&document.referrer.indexOf(location.host)<0)s.push('ref='+document.referrer.split('?')[0]);` +
  `s.push('wejscie='+location.pathname);sessionStorage.setItem('cv-src',s.join(' · '))}}catch(e){}` +
  `var sent=0;window.cvLead=function(){if(sent)return;sent=1;` +
  `gtag('event','conversion',{send_to:'${LEAD}',value:1.0,currency:'PLN'});gtag('event','generate_lead',{form_name:'kontakt'})};` +
  `document.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href^="tel:"],a[href^="mailto:"]');if(!a)return;` +
  `var t=a.getAttribute('href').indexOf('tel:')===0;` +
  `gtag('event','conversion',{send_to:'${CALL}',value:1.0,currency:'PLN'});gtag('event',t?'phone_click':'email_click',{link_url:a.getAttribute('href'),page:location.pathname})},true)` +
  `})();</script>`;
