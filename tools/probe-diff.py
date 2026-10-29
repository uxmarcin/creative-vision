import json,sys
A=json.load(open(sys.argv[1])); B=json.load(open(sys.argv[2]))
for page in A:
  if page not in B: print('missing',page); continue
  ra, rb = A[page], B[page]
  ka, kb = list(ra), list(rb)
  if ka!=kb: print(page,'ROOTS DIFFER',ka,kb)
  for k in ka:
    if k not in rb: continue
    a,b=ra[k],rb[k]
    hdr=f"{page} | {k[:30]} y {a['y']}→{b['y']} x {a['x']}→{b['x']} w {a['w']}→{b['w']} n {len(a['n'])}/{len(b['n'])}"
    na,nb=a['n'],b['n']; shown=0; out=[]
    for i,(x,y) in enumerate(zip(na,nb)):
      if x[0]!=y[0]: out.append(f"   STRUCT {i} {x[0][:50]} vs {y[0][:50]}"); break
      d=[abs(x[j]-y[j]) for j in (1,2,3,4)]
      if max(d)>2 or x[5:]!=y[5:]:
        out.append(f"   {i} {x[0][:60]} live{x[1:5]}{x[5:9] if x[5:]!=y[5:] else ''} local{y[1:5]}{y[5:9] if x[5:]!=y[5:] else ''}"); shown+=1
        if shown>=6: break
    if out or abs(a['y']-b['y'])>2: print(hdr); print('\n'.join(out))
