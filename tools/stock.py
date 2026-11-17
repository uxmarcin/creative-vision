import json,urllib.request,urllib.parse,os,io
from PIL import Image
Q={'strona-firmowa-ktora-zbiera-zapytania':'website wireframe laptop desk','ile-kosztuje-strona-internetowa':'calculator invoice laptop desk','ux-karty-produktu':'online shopping phone product','ui-a-ux-roznice':'ui wireframe sketch paper','ai-w-sklepie-internetowym':'artificial intelligence chat laptop','sklep-a-zakupy-przez-asystentow-ai':'smartphone assistant shopping','ile-kosztuje-sklep-internetowy':'ecommerce parcel laptop payment','co-to-jest-prestashop':'online store laptop screen','moduly-prestashop':'payment terminal card contactless','projektowanie-ux-ui':'ux design sticky notes wireframe','jak-stworzyc-strone-internetowa':'web design workspace monitor','cennik':'price planning notebook calculator','jak-wybrac-agencje-e-commerce':'contract signing desk pen','jak-zalozyc-sklep-internetowy':'packing orders small business boxes','ile-kosztuje-aplikacja-mobilna':'mobile app design smartphone','jaka-platforma-sklepu-internetowego':'laptop comparison choice desk'}
H={'User-Agent':'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36','Accept':'application/json'}
os.makedirs('stock',exist_ok=True);meta={}
for slug,q in Q.items():
  try:
    r=json.load(urllib.request.urlopen(urllib.request.Request('https://unsplash.com/napi/search/photos?per_page=12&orientation=landscape&query='+urllib.parse.quote(q),headers=H),timeout=30))
    n=0
    for p in r.get('results',[]):
      if p.get('premium') or p.get('plus') or 'plus.unsplash' in p['urls']['raw']: continue
      n+=1
      d=urllib.request.urlopen(urllib.request.Request(p['urls']['raw']+'&w=1600&q=80&fm=jpg',headers=H),timeout=60).read()
      im=Image.open(io.BytesIO(d)).convert('RGB');th=round(im.width/1.6)
      if th<im.height: t=(im.height-th)//2;im=im.crop((0,t,im.width,t+th))
      im.resize((1600,1000)).save(f'stock/{slug}-{n}.webp',quality=84)
      meta[f'{slug}-{n}']=[p['id'],p['user']['name'],p['links']['html'],p.get('alt_description')]
      if n==4: break
    print(slug,n)
  except Exception as e: print('fail',slug,e)
json.dump(meta,open('stock/meta.json','w'),ensure_ascii=False,indent=1)
