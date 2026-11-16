/* Creative Vision — hero strony głównej: płynący mesh gradient w kolorach marki (WebGL),
   wejście hasła słowo po słowie i magnetyczne przyciski. */
(() => {
  const sec = document.querySelector('.cv-mhs'); const hero = document.querySelector('.cv-mh');
  if (!sec || !hero) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  // ciągły gradient przez wszystkie słowa drugiej linii
  const fitG = () => { const g = hero.querySelector('.ln.g'); if (!g) return; let W = 0; g.querySelectorAll('.wd').forEach((w) => { W = Math.max(W, w.offsetLeft + w.offsetWidth - g.offsetLeft); });
    g.querySelectorAll('.wd').forEach((w) => { w.style.backgroundSize = W + 'px 100%'; w.style.backgroundPosition = -(w.offsetLeft - g.offsetLeft) + 'px 0'; }); };
  fitG(); addEventListener('resize', fitG); document.fonts && document.fonts.ready.then(fitG);
  requestAnimationFrame(() => requestAnimationFrame(() => hero.classList.add('go')));
  if (!reduce && matchMedia('(pointer:fine)').matches) hero.querySelectorAll('.cv-mag').forEach((el) => {
    el.addEventListener('pointermove', (e) => { const r = el.getBoundingClientRect(); const x = (e.clientX - r.left - r.width / 2) / r.width, y = (e.clientY - r.top - r.height / 2) / r.height; el.style.transform = `translate(${x * 26}px,${y * 18}px)`; });
    el.addEventListener('pointerleave', () => { el.style.transform = ''; });
  });
  const cv = hero.querySelector('.cv-mesh'); if (!cv) return;
  const gl = cv.getContext('webgl', { antialias: false, premultipliedAlpha: false }); if (!gl) return;
  const S = { m: 1, speed: 3, int: 1.5, scale: 1.55, mask: 1, grain: 0 };
  const vs = 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}';
  const fs = `precision highp float;
uniform vec2 R;uniform float T;uniform vec2 MS;uniform float MODE,INT,SC,MASK,GR;
const vec3 C0=vec3(.914,.408,.373),C1=vec3(.878,.247,.231),C2=vec3(.675,.125,.412),C3=vec3(.533,.251,.624),C4=vec3(.243,.388,.937),C5=vec3(.318,.729,.831);
vec3 pal(float t){t=fract(t)*6.;float f=smoothstep(0.,1.,fract(t));
 if(t<1.)return mix(C0,C1,f);if(t<2.)return mix(C1,C2,f);if(t<3.)return mix(C2,C3,f);if(t<4.)return mix(C3,C4,f);if(t<5.)return mix(C4,C5,f);return mix(C5,C0,f);}
float h21(vec2 p){p=fract(p*vec2(123.34,456.21));p+=dot(p,p+45.32);return fract(p.x*p.y);}
float n2(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);
 return mix(mix(h21(i),h21(i+vec2(1,0)),f.x),mix(h21(i+vec2(0,1)),h21(i+vec2(1,1)),f.x),f.y);}
float fbm(vec2 p){float v=0.,a=.5;mat2 r=mat2(.8,.6,-.6,.8);for(int i=0;i<5;i++){v+=a*n2(p);p=r*p*2.02;a*=.5;}return v;}
mat2 rot(float a){float c=cos(a),s=sin(a);return mat2(c,-s,s,c);}
float sdSeg(vec2 p,vec2 a,vec2 b){vec2 pa=p-a,ba=b-a;float h=clamp(dot(pa,ba)/dot(ba,ba),0.,1.);return length(pa-ba*h);}
float sig2(vec2 p){p.x+=.45;
 float r=abs(length(p)-.6)-.11;float ang=atan(p.y,p.x);float w=(abs(ang)-.78)*length(p);float c=max(r,-w);
 float s=sdSeg(p,vec2(.6,.92),vec2(.76,.36))-.1;
 float v=min(sdSeg(p,vec2(.96,.58),vec2(1.24,-.62)),sdSeg(p,vec2(1.24,-.62),vec2(1.52,.58)))-.105;
 return min(c,min(s,v));}
float map(vec3 q){float d2=sig2(q.xy);vec2 w=vec2(d2,abs(q.z)-.2);return min(max(w.x,w.y),0.)+length(max(w,0.));}
vec3 sigScene(vec2 p,float T,vec2 MS,float SC,float INT,out float hit){
 vec3 ro=vec3(0.,0.,6.8);vec3 rd=normalize(vec3(p-vec2(.56,.02),-1.7));
 float ay=sin(T*.22)*.45-.3+MS.x*1.1,ax=sin(T*.17)*.18+.1-MS.y*.7;
 float t=0.;hit=0.;vec3 q;
 for(int i=0;i<110;i++){vec3 pp=ro+rd*t;q=pp/SC;q.xz=rot(ay)*q.xz;q.yz=rot(ax)*q.yz;float d=map(q)*SC;if(d<.0015){hit=1.;break;}t+=d;if(t>12.)break;}
 if(hit<.5)return vec3(0.);
 vec2 e=vec2(.002,0.);vec3 n=normalize(vec3(map(q+e.xyy)-map(q-e.xyy),map(q+e.yxy)-map(q-e.yxy),map(q+e.yyx)-map(q-e.yyx)));
 vec3 an=abs(n);float k=16.;vec3 g=fract(q*k);vec3 l=smoothstep(.86,.97,g)+smoothstep(.14,.03,g);
 float gl2=an.z>an.x&&an.z>an.y?max(l.x,l.y):(an.x>an.y?max(l.y,l.z):max(l.x,l.z));
 vec3 ld=normalize(vec3(-.4,.6,.8));float dif=.35+.65*max(dot(n,ld),0.);
 vec3 c=pal(q.x*.28+q.y*.12+T*.03);
 float fr=pow(1.-abs(n.z),2.);
 return vec3(.02)+gl2*mix(vec3(1.),c,.55)*dif*INT*.9+c*fr*.35*INT;}
void main(){
 vec2 uv=gl_FragCoord.xy/R;float asp=R.x/R.y;
 vec2 p=(gl_FragCoord.xy-.5*R)/R.y; p+=MS*.12; p/=SC;
 vec3 col=vec3(0.);float a=0.;
 if(MODE<.5){ // jedwab: domain warping
  vec2 q=vec2(fbm(p*1.4+vec2(0.,T*.06)),fbm(p*1.4+vec2(5.2,1.3)-T*.05));
  float r=fbm(p*1.4+3.2*q+vec2(1.7,9.2)+T*.03);
  col=pal(r*1.3+q.x*.6+T*.015);a=pow(smoothstep(.3,.95,r),1.6)*1.25;
 }else if(MODE<1.5){ // mesh: ruchome punkty kolorów
  vec3 acc=vec3(0.);float ws=0.;float md=9.;
  for(int i=0;i<6;i++){float fi=float(i);
   vec2 c=vec2(.55*asp*.5*sin(T*.11*(1.+fi*.13)+fi*1.7)+.25*asp*.5,.45*cos(T*.09*(1.+fi*.17)+fi*2.3));
   float d=length(p-c);md=min(md,d);float w=1./pow(d*d+.09,1.15);
   vec3 ci=fi<.5?C0:fi<1.5?C1:fi<2.5?C2:fi<3.5?C3:fi<4.5?C4:C5;acc+=ci*w;ws+=w;}
  col=acc/ws;col=clamp(mix(vec3(dot(col,vec3(.3,.59,.11))),col,1.7),0.,1.);col=pow(col,vec3(.9));
  a=pow(smoothstep(.78,.0,md),1.4);
  a*=.8+.2*fbm(p*2.+T*.05);
 }else if(MODE<2.5){ // zorza
  float y=uv.y;vec3 acc=vec3(0.);
  for(int i=0;i<3;i++){float fi=float(i);
   float c=.62-fi*.12+.12*(fbm(vec2(p.x*1.3+T*.07+fi*3.,T*.04+fi))-.5)*2.;
   float band=exp(-pow((y-c)*(5.+fi*2.),2.));
   float str=.55+.45*fbm(vec2(p.x*9.+fi*7.,T*.25));
   acc+=pal(p.x*.25+fi*.18+T*.02)*band*str;}
  col=acc;a=clamp(length(acc)*1.2,0.,1.);col=col/max(a,.001);
 }else if(MODE<3.5){ // wstęgi
  vec3 acc=vec3(0.);
  for(int i=0;i<5;i++){float fi=float(i);
   float y0=.12*sin(p.x*1.6+T*.35+fi*1.3)+.08*sin(p.x*3.1-T*.25+fi*2.1)+(fi-2.)*.07+.08;
   float d=abs(p.y-y0);
   acc+=pal(fi*.17+p.x*.2+T*.02)*(exp(-d*55.)*.9+exp(-d*9.)*.35);}
  col=acc;a=clamp(max(max(acc.r,acc.g),acc.b),0.,1.);col=col/max(a,.001);
 }else if(MODE<4.5){ // lawa: metaballe
  float f=0.;vec3 acc=vec3(0.);
  for(int i=0;i<7;i++){float fi=float(i);
   vec2 c=vec2(.6*sin(T*.21*(1.+fi*.1)+fi*2.1)+.35,.42*sin(T*.17*(1.+fi*.15)+fi*1.3));
   float r=.11+.05*sin(fi*3.1);float v=r*r/dot(p-c,p-c);f+=v;acc+=pal(fi/7.+T*.01)*v;}
  col=acc/max(f,.001);a=smoothstep(.75,1.15,f)+.35*smoothstep(.15,.9,f);
 }else if(MODE>5.5){ // sygnet 3D (8 = z mesh w tle)
  float hit;vec3 sc=sigScene(p*SC,T,MS,1.,INT,hit);
  vec3 bgc=vec3(0.);float ba=0.;
  if(MODE>6.5){vec3 acc=vec3(0.);float ws=0.;for(int i=0;i<6;i++){float fi=float(i);vec2 c=vec2(.5*sin(T*.11*(1.+fi*.13)+fi*1.7)+.35,.42*cos(T*.09*(1.+fi*.17)+fi*2.3));float d=length(p-c);float w=1./pow(d*d+.03,1.4);vec3 ci=fi<.5?C0:fi<1.5?C1:fi<2.5?C2:fi<3.5?C3:fi<4.5?C4:C5;acc+=ci*w;ws+=w;}bgc=acc/ws;ba=.38;}
  col=mix(bgc,sc,hit);a=mix(ba,1.,hit);
  gl_FragColor=vec4(mix(vec3(.043),col,a*mix(1.,smoothstep(-.05,.85,uv.x+.15*(1.-uv.y)),MASK*(1.-hit)))+(h21(gl_FragCoord.xy+fract(T)*97.)-.5)*GR,1.);return;
 }else{ // kula
  vec2 c=vec2(.42*asp*.5+.12*sin(T*.2),.05*cos(T*.17));vec2 d=p-c;float r=length(d);
  float ang=atan(d.y,d.x)/6.2831+T*.03+.25*fbm(d*3.+T*.1);
  col=pal(ang);a=smoothstep(.62,.18,r)+.25*smoothstep(.95,.3,r);
  a*=.85+.15*fbm(d*6.-T*.2);
 }
 a=clamp(a*INT,0.,1.);
 float m=mix(1.,smoothstep(.12,.95,uv.x+.12*(1.-uv.y)),MASK);
 vec3 bg=vec3(.043);
 vec3 o=mix(bg,col,a*m);
 o+=(h21(gl_FragCoord.xy+fract(T)*97.)-.5)*GR;
 gl_FragColor=vec4(o,1.);
}`;
  const sh = (t, s) => { const o = gl.createShader(t); gl.shaderSource(o, s); gl.compileShader(o); return o; };
  const pr = gl.createProgram(); gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(pr);
  if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) return; gl.useProgram(pr);
  const b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  const loc = gl.getAttribLocation(pr, 'p'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  const U = {}; for (const n of ['R', 'T', 'MS', 'MODE', 'INT', 'SC', 'MASK', 'GR']) U[n] = gl.getUniformLocation(pr, n);
  const Q = 0.5;
  const size = () => { const w = Math.max(1, Math.round(cv.clientWidth * Q)), h = Math.max(1, Math.round(cv.clientHeight * Q)); if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; gl.viewport(0, 0, w, h); } };
  let mx = 0, my = 0, tx = 0, ty = 0;
  addEventListener('pointermove', (e) => { tx = e.clientX / innerWidth - 0.5; ty = -(e.clientY / innerHeight - 0.5); }, { passive: true });
  let t = 0, last = performance.now(), vis = true, onscreen = true, raf = 0;
  const draw = () => { size(); mx += (tx - mx) * 0.04; my += (ty - my) * 0.04;
    gl.uniform2f(U.R, cv.width, cv.height); gl.uniform1f(U.T, t + 20); gl.uniform2f(U.MS, mx, my); gl.uniform1f(U.MODE, S.m); gl.uniform1f(U.INT, S.int); gl.uniform1f(U.SC, S.scale); gl.uniform1f(U.MASK, S.mask); gl.uniform1f(U.GR, S.grain);
    gl.drawArrays(gl.TRIANGLES, 0, 3); };
  const frame = (now) => { raf = 0; if (!vis || !onscreen) return; const dt = Math.min(0.05, (now - last) / 1000); last = now; t += dt * S.speed; draw(); raf = requestAnimationFrame(frame); };
  const start = () => { if (!raf && vis && onscreen) { last = performance.now(); raf = requestAnimationFrame(frame); } };
  if (reduce) { draw(); addEventListener('resize', draw); return; }
  document.addEventListener('visibilitychange', () => { vis = !document.hidden; start(); });
  new IntersectionObserver((es) => { onscreen = es[0].isIntersecting; start(); }).observe(hero);
  start();
})();
