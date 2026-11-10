// Znacznik Google Ads z trybem zgód: skrypt Google ładuje się dopiero po zgodzie marketingowej
// z banera cookies (zdarzenie cv-consent, zapis w localStorage cv-consent-v1).
// Konwersja: udane wysłanie formularza kontaktowego (POST do formsubmit.co).
const ID = 'AW-18567029172';
const LEAD = ID + '/7CwaCPqD1bUdELTDuZVF';
export const GTAG_HTML = `<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}` +
  `gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});` +
  `(function(){var on=0;function load(){if(on)return;on=1;var j=document.createElement('script');j.async=true;` +
  `j.src='https://www.googletagmanager.com/gtag/js?id=${ID}';document.head.appendChild(j);gtag('js',new Date());gtag('config','${ID}')}` +
  `function apply(c){if(c&&c.marketing){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted'});load()}}` +
  `try{apply(JSON.parse(localStorage.getItem('cv-consent-v1')||'null'))}catch(e){}` +
  `window.addEventListener('cv-consent',function(e){apply(e.detail)});` +
  `var f=window.fetch;if(f)window.fetch=function(u,o){var p=f.apply(this,arguments);` +
  `try{if(o&&o.method==='POST'&&String(u).indexOf('formsubmit.co')>-1)p.then(function(r){if(r&&r.ok&&on)gtag('event','conversion',{send_to:'${LEAD}',value:1.0,currency:'PLN'})})}catch(e){}` +
  `return p}})();</script>`;
