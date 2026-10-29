/* Creative Vision — płynna poświata za kursorem (symulacja cieczy w WebGL).
   Algorytm stabilnej cieczy (adwekcja, wirowość, ciśnienie) w duchu
   WebGL-Fluid-Simulation P. Dobryakova (MIT); kolory z gradientu marki. */
(() => {
  if (!matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const CFG = {
    SIM: 128, DYE: 1024,
    DENSITY_DISSIPATION: 3.5, VELOCITY_DISSIPATION: 2,
    PRESSURE: 0.1, PRESSURE_ITER: 20, CURL: 3,
    SPLAT_RADIUS: 0.2, SPLAT_FORCE: 6000,
    DIM: 0.13,
  };
  // gradient marki: koral → czerwień → magenta → fiolet → niebieski → cyjan
  const STOPS = [[233, 104, 95], [224, 63, 59], [172, 32, 105], [136, 64, 159], [62, 99, 239], [81, 186, 212]];
  const brand = (t) => {
    t = ((t % 2) + 2) % 2; if (t > 1) t = 2 - t;
    const f = t * (STOPS.length - 1), i = Math.min(Math.floor(f), STOPS.length - 2), k = f - i;
    const a = STOPS[i], b = STOPS[i + 1];
    return a.map((v, j) => (v + (b[j] - v) * k) / 255);
  };

  const canvas = document.createElement('canvas');
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:2147483646;mix-blend-mode:screen';
  document.body.appendChild(canvas);

  const params = { alpha: true, depth: false, stencil: false, antialias: false, preserveDrawingBuffer: false, premultipliedAlpha: true };
  let gl = canvas.getContext('webgl2', params);
  const isGL2 = !!gl;
  if (!gl) gl = canvas.getContext('webgl', params);
  if (!gl) { canvas.remove(); return; }

  let halfFloat, linear;
  if (isGL2) { gl.getExtension('EXT_color_buffer_float'); linear = gl.getExtension('OES_texture_float_linear'); }
  else { halfFloat = gl.getExtension('OES_texture_half_float'); linear = gl.getExtension('OES_texture_half_float_linear'); }
  const HF = isGL2 ? gl.HALF_FLOAT : halfFloat && halfFloat.HALF_FLOAT_OES;
  if (!HF) { canvas.remove(); return; }
  if (!linear) CFG.DYE = 256;
  gl.clearColor(0, 0, 0, 0);

  const supportFmt = (internal, format) => {
    const t = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, t);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texImage2D(gl.TEXTURE_2D, 0, internal, 4, 4, 0, format, HF, null);
    const fb = gl.createFramebuffer(); gl.bindFramebuffer(gl.FRAMEBUFFER, fb);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, t, 0);
    const ok = gl.checkFramebufferStatus(gl.FRAMEBUFFER) === gl.FRAMEBUFFER_COMPLETE;
    gl.deleteFramebuffer(fb); gl.deleteTexture(t); return ok;
  };
  const fmt = (internal, format) => {
    if (supportFmt(internal, format)) return { internal, format };
    if (!isGL2) return null;
    if (internal === gl.R16F) return fmt(gl.RG16F, gl.RG);
    if (internal === gl.RG16F) return fmt(gl.RGBA16F, gl.RGBA);
    return null;
  };
  const F_RGBA = isGL2 ? fmt(gl.RGBA16F, gl.RGBA) : fmt(gl.RGBA, gl.RGBA);
  const F_RG = isGL2 ? fmt(gl.RG16F, gl.RG) : F_RGBA;
  const F_R = isGL2 ? fmt(gl.R16F, gl.RED) : F_RGBA;
  if (!F_RGBA || !F_RG || !F_R) { canvas.remove(); return; }

  const sh = (type, src) => { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; };
  const VS = sh(gl.VERTEX_SHADER, `precision highp float;
attribute vec2 aPosition; varying vec2 vUv,vL,vR,vT,vB; uniform vec2 texelSize;
void main(){vUv=aPosition*.5+.5;vL=vUv-vec2(texelSize.x,0.);vR=vUv+vec2(texelSize.x,0.);vT=vUv+vec2(0.,texelSize.y);vB=vUv-vec2(0.,texelSize.y);gl_Position=vec4(aPosition,0.,1.);}`);
  const prog = (src) => {
    const p = gl.createProgram(); gl.attachShader(p, VS); gl.attachShader(p, sh(gl.FRAGMENT_SHADER, 'precision highp float;precision highp sampler2D;\n' + src)); gl.linkProgram(p);
    const u = {}; const n = gl.getProgramParameter(p, gl.ACTIVE_UNIFORMS);
    for (let i = 0; i < n; i++) { const nm = gl.getActiveUniform(p, i).name; u[nm] = gl.getUniformLocation(p, nm); }
    return { p, u, bind() { gl.useProgram(p); } };
  };

  const P = {
    copy: prog(`varying highp vec2 vUv;uniform sampler2D uTexture;void main(){gl_FragColor=texture2D(uTexture,vUv);}`),
    clear: prog(`varying highp vec2 vUv;uniform sampler2D uTexture;uniform float value;void main(){gl_FragColor=value*texture2D(uTexture,vUv);}`),
    splat: prog(`varying vec2 vUv;uniform sampler2D uTarget;uniform float aspectRatio;uniform vec3 color;uniform vec2 point;uniform float radius;
void main(){vec2 p=vUv-point.xy;p.x*=aspectRatio;vec3 s=exp(-dot(p,p)/radius)*color;vec3 b=texture2D(uTarget,vUv).xyz;gl_FragColor=vec4(b+s,1.);}`),
    advection: prog(`varying vec2 vUv;uniform sampler2D uVelocity,uSource;uniform vec2 texelSize,dyeTexelSize;uniform float dt,dissipation;
vec4 bil(sampler2D s,vec2 uv,vec2 ts){vec2 st=uv/ts-.5;vec2 i=floor(st);vec2 f=fract(st);vec4 a=texture2D(s,(i+vec2(.5,.5))*ts),b=texture2D(s,(i+vec2(1.5,.5))*ts),c=texture2D(s,(i+vec2(.5,1.5))*ts),d=texture2D(s,(i+vec2(1.5,1.5))*ts);return mix(mix(a,b,f.x),mix(c,d,f.x),f.y);}
void main(){
#ifdef MANUAL
vec2 coord=vUv-dt*bil(uVelocity,vUv,texelSize).xy*texelSize;vec4 r=bil(uSource,coord,dyeTexelSize);
#else
vec2 coord=vUv-dt*texture2D(uVelocity,vUv).xy*texelSize;vec4 r=texture2D(uSource,coord);
#endif
gl_FragColor=r/(1.+dissipation*dt);}`.replace('#ifdef MANUAL', linear ? '#if 0' : '#if 1')),
    divergence: prog(`varying highp vec2 vUv,vL,vR,vT,vB;uniform sampler2D uVelocity;
void main(){float L=texture2D(uVelocity,vL).x,R=texture2D(uVelocity,vR).x,T=texture2D(uVelocity,vT).y,B=texture2D(uVelocity,vB).y;vec2 C=texture2D(uVelocity,vUv).xy;
if(vL.x<0.)L=-C.x;if(vR.x>1.)R=-C.x;if(vT.y>1.)T=-C.y;if(vB.y<0.)B=-C.y;gl_FragColor=vec4(.5*(R-L+T-B),0.,0.,1.);}`),
    curl: prog(`varying highp vec2 vUv,vL,vR,vT,vB;uniform sampler2D uVelocity;
void main(){float L=texture2D(uVelocity,vL).y,R=texture2D(uVelocity,vR).y,T=texture2D(uVelocity,vT).x,B=texture2D(uVelocity,vB).x;gl_FragColor=vec4(.5*(R-L-T+B),0.,0.,1.);}`),
    vorticity: prog(`varying vec2 vUv,vL,vR,vT,vB;uniform sampler2D uVelocity,uCurl;uniform float curl,dt;
void main(){float L=texture2D(uCurl,vL).x,R=texture2D(uCurl,vR).x,T=texture2D(uCurl,vT).x,B=texture2D(uCurl,vB).x,C=texture2D(uCurl,vUv).x;
vec2 f=.5*vec2(abs(T)-abs(B),abs(R)-abs(L));f/=length(f)+.0001;f*=curl*C;f.y*=-1.;vec2 v=texture2D(uVelocity,vUv).xy+f*dt;v=min(max(v,-1000.),1000.);gl_FragColor=vec4(v,0.,1.);}`),
    pressure: prog(`varying highp vec2 vUv,vL,vR,vT,vB;uniform sampler2D uPressure,uDivergence;
void main(){float L=texture2D(uPressure,vL).x,R=texture2D(uPressure,vR).x,T=texture2D(uPressure,vT).x,B=texture2D(uPressure,vB).x,d=texture2D(uDivergence,vUv).x;gl_FragColor=vec4((L+R+B+T-d)*.25,0.,0.,1.);}`),
    gradient: prog(`varying highp vec2 vUv,vL,vR,vT,vB;uniform sampler2D uPressure,uVelocity;
void main(){float L=texture2D(uPressure,vL).x,R=texture2D(uPressure,vR).x,T=texture2D(uPressure,vT).x,B=texture2D(uPressure,vB).x;vec2 v=texture2D(uVelocity,vUv).xy;v.xy-=vec2(R-L,T-B);gl_FragColor=vec4(v,0.,1.);}`),
    display: prog(`varying vec2 vUv,vL,vR,vT,vB;uniform sampler2D uTexture;uniform vec2 texelSize;
void main(){vec3 c=texture2D(uTexture,vUv).rgb;
float dx=length(texture2D(uTexture,vR).rgb)-length(texture2D(uTexture,vL).rgb);float dy=length(texture2D(uTexture,vT).rgb)-length(texture2D(uTexture,vB).rgb);
vec3 n=normalize(vec3(dx,dy,length(texelSize)));float diffuse=clamp(dot(n,vec3(0.,0.,1.))+.7,.7,1.);c*=diffuse;
float a=max(c.r,max(c.g,c.b));gl_FragColor=vec4(c,a);}`),
  };

  gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, -1, 1, 1, 1, 1, -1]), gl.STATIC_DRAW);
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, gl.createBuffer());
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array([0, 1, 2, 0, 2, 3]), gl.STATIC_DRAW);
  gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0); gl.enableVertexAttribArray(0);
  const blit = (t) => {
    if (t == null) { gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight); gl.bindFramebuffer(gl.FRAMEBUFFER, null); }
    else { gl.viewport(0, 0, t.w, t.h); gl.bindFramebuffer(gl.FRAMEBUFFER, t.fbo); }
    gl.drawElements(gl.TRIANGLES, 6, gl.UNSIGNED_SHORT, 0);
  };

  const fbo = (w, h, f, filter) => {
    gl.activeTexture(gl.TEXTURE0);
    const tex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tex);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, filter); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, filter);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texImage2D(gl.TEXTURE_2D, 0, f.internal, w, h, 0, f.format, HF, null);
    const fb = gl.createFramebuffer(); gl.bindFramebuffer(gl.FRAMEBUFFER, fb);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, tex, 0);
    gl.viewport(0, 0, w, h); gl.clear(gl.COLOR_BUFFER_BIT);
    return { tex, fbo: fb, w, h, tx: 1 / w, ty: 1 / h, attach(id) { gl.activeTexture(gl.TEXTURE0 + id); gl.bindTexture(gl.TEXTURE_2D, tex); return id; } };
  };
  const dbl = (w, h, f, filter) => {
    let a = fbo(w, h, f, filter), b = fbo(w, h, f, filter);
    return { w, h, tx: 1 / w, ty: 1 / h, get read() { return a; }, get write() { return b; }, swap() { const t = a; a = b; b = t; } };
  };
  const res = (r) => {
    let ar = gl.drawingBufferWidth / gl.drawingBufferHeight; if (ar < 1) ar = 1 / ar;
    const mn = Math.round(r), mx = Math.round(r * ar);
    return gl.drawingBufferWidth > gl.drawingBufferHeight ? { w: mx, h: mn } : { w: mn, h: mx };
  };

  let dye, velocity, divergence, curlT, pressure;
  const filt = linear ? gl.LINEAR : gl.NEAREST;
  const init = () => {
    const s = res(CFG.SIM), d = res(CFG.DYE);
    gl.disable(gl.BLEND);
    dye = dbl(d.w, d.h, F_RGBA, filt);
    velocity = dbl(s.w, s.h, F_RG, filt);
    divergence = fbo(s.w, s.h, F_R, gl.NEAREST);
    curlT = fbo(s.w, s.h, F_R, gl.NEAREST);
    pressure = dbl(s.w, s.h, F_R, gl.NEAREST);
  };
  const resize = () => {
    const dpr = Math.min(devicePixelRatio || 1, 2);
    const w = Math.floor(innerWidth * dpr), h = Math.floor(innerHeight * dpr);
    if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; return true; }
    return false;
  };
  resize(); init();

  const splat = (x, y, dx, dy, c) => {
    P.splat.bind();
    gl.uniform1i(P.splat.u.uTarget, velocity.read.attach(0));
    gl.uniform1f(P.splat.u.aspectRatio, canvas.width / canvas.height);
    gl.uniform2f(P.splat.u.point, x, y);
    gl.uniform3f(P.splat.u.color, dx, dy, 0);
    let r = CFG.SPLAT_RADIUS / 100; const ar = canvas.width / canvas.height; if (ar > 1) r *= ar;
    gl.uniform1f(P.splat.u.radius, r);
    blit(velocity.write); velocity.swap();
    gl.uniform1i(P.splat.u.uTarget, dye.read.attach(0));
    gl.uniform3f(P.splat.u.color, c[0], c[1], c[2]);
    blit(dye.write); dye.swap();
  };

  const step = (dt) => {
    gl.disable(gl.BLEND);
    P.curl.bind(); gl.uniform2f(P.curl.u.texelSize, velocity.tx, velocity.ty); gl.uniform1i(P.curl.u.uVelocity, velocity.read.attach(0)); blit(curlT);
    P.vorticity.bind(); gl.uniform2f(P.vorticity.u.texelSize, velocity.tx, velocity.ty); gl.uniform1i(P.vorticity.u.uVelocity, velocity.read.attach(0));
    gl.uniform1i(P.vorticity.u.uCurl, curlT.attach(1)); gl.uniform1f(P.vorticity.u.curl, CFG.CURL); gl.uniform1f(P.vorticity.u.dt, dt); blit(velocity.write); velocity.swap();
    P.divergence.bind(); gl.uniform2f(P.divergence.u.texelSize, velocity.tx, velocity.ty); gl.uniform1i(P.divergence.u.uVelocity, velocity.read.attach(0)); blit(divergence);
    P.clear.bind(); gl.uniform1i(P.clear.u.uTexture, pressure.read.attach(0)); gl.uniform1f(P.clear.u.value, CFG.PRESSURE); blit(pressure.write); pressure.swap();
    P.pressure.bind(); gl.uniform2f(P.pressure.u.texelSize, velocity.tx, velocity.ty); gl.uniform1i(P.pressure.u.uDivergence, divergence.attach(0));
    for (let i = 0; i < CFG.PRESSURE_ITER; i++) { gl.uniform1i(P.pressure.u.uPressure, pressure.read.attach(1)); blit(pressure.write); pressure.swap(); }
    P.gradient.bind(); gl.uniform2f(P.gradient.u.texelSize, velocity.tx, velocity.ty); gl.uniform1i(P.gradient.u.uPressure, pressure.read.attach(0));
    gl.uniform1i(P.gradient.u.uVelocity, velocity.read.attach(1)); blit(velocity.write); velocity.swap();
    P.advection.bind(); gl.uniform2f(P.advection.u.texelSize, velocity.tx, velocity.ty);
    if (!linear) gl.uniform2f(P.advection.u.dyeTexelSize, velocity.tx, velocity.ty);
    const vid = velocity.read.attach(0); gl.uniform1i(P.advection.u.uVelocity, vid); gl.uniform1i(P.advection.u.uSource, vid);
    gl.uniform1f(P.advection.u.dt, dt); gl.uniform1f(P.advection.u.dissipation, CFG.VELOCITY_DISSIPATION); blit(velocity.write); velocity.swap();
    if (!linear) gl.uniform2f(P.advection.u.dyeTexelSize, dye.tx, dye.ty);
    gl.uniform1i(P.advection.u.uVelocity, velocity.read.attach(0)); gl.uniform1i(P.advection.u.uSource, dye.read.attach(1));
    gl.uniform1f(P.advection.u.dissipation, CFG.DENSITY_DISSIPATION); blit(dye.write); dye.swap();
  };
  const render = () => {
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA); gl.enable(gl.BLEND);
    gl.bindFramebuffer(gl.FRAMEBUFFER, null); gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight); gl.clear(gl.COLOR_BUFFER_BIT);
    P.display.bind(); gl.uniform2f(P.display.u.texelSize, 1 / gl.drawingBufferWidth, 1 / gl.drawingBufferHeight);
    gl.uniform1i(P.display.u.uTexture, dye.read.attach(0)); blit(null);
  };

  // wskaźnik
  const ptr = { x: 0, y: 0, px: 0, py: 0, dx: 0, dy: 0, moved: false, seen: false, color: [0, 0, 0] };
  const colorAt = (cx, cy, now) => {
    const t = cx / innerWidth * 0.55 + (cy + scrollY) / 1400 + now / 30000;
    const c = brand(t); const k = CFG.DIM;
    return [c[0] * k, c[1] * k, c[2] * k];
  };
  addEventListener('pointermove', (e) => {
    const dpr = canvas.width / innerWidth;
    const x = e.clientX * dpr / canvas.width, y = 1 - e.clientY * dpr / canvas.height;
    if (!ptr.seen) { ptr.seen = true; ptr.x = ptr.px = x; ptr.y = ptr.py = y; }
    ptr.px = ptr.x; ptr.py = ptr.y; ptr.x = x; ptr.y = y;
    const ar = canvas.width / canvas.height;
    ptr.dx = (ptr.x - ptr.px) * (ar < 1 ? ar : 1);
    ptr.dy = (ptr.y - ptr.py) / (ar > 1 ? ar : 1);
    ptr.moved = Math.abs(ptr.dx) > 0 || Math.abs(ptr.dy) > 0;
    ptr.color = colorAt(e.clientX, e.clientY, performance.now());
  }, { passive: true });

  let last = performance.now(), running = true;
  document.addEventListener('visibilitychange', () => { running = !document.hidden; if (running) { last = performance.now(); requestAnimationFrame(loop); } });
  function loop(now) {
    if (!running) return;
    const dt = Math.min((now - last) / 1000, 0.016666); last = now;
    if (resize()) init();
    if (ptr.moved) { ptr.moved = false; splat(ptr.x, ptr.y, ptr.dx * CFG.SPLAT_FORCE, ptr.dy * CFG.SPLAT_FORCE, ptr.color); }
    step(dt); render();
    requestAnimationFrame(loop);
  }
  requestAnimationFrame(loop);
})();
