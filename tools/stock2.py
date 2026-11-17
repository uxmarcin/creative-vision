import re,subprocess,os,io,json,urllib.parse
from PIL import Image
Q={'strona-firmowa-ktora-zbiera-zapytania':['website laptop','web design'],'ile-kosztuje-strona-internetowa':['calculator desk','calculator'],'ux-karty-produktu':['online shopping','online shopping'],'ui-a-ux-roznice':['wireframe','design sketch'],'ai-w-sklepie-internetowym':['technology laptop','technology'],'sklep-a-zakupy-przez-asystentow-ai':['smartphone hand','mobile shopping'],'ile-kosztuje-sklep-internetowy':['credit card laptop','ecommerce'],'co-to-jest-prestashop':['laptop store','online store'],'moduly-prestashop':['payment card','payment'],'projektowanie-ux-ui':['sticky notes','planning'],'jak-stworzyc-strone-internetowa':['workspace monitor','workspace'],'cennik':['notebook pen desk','budget'],'jak-wybrac-agencje-e-commerce':['contract','handshake'],'jak-zalozyc-sklep-internetowy':['packing boxes','shipping'],'ile-kosztuje-aplikacja-mobilna':['phone app','mobile app'],'jaka-platforma-sklepu-internetowego':['laptop coffee desk','choice']}
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36"
def get(u):
  return subprocess.run(['curl','-sS','-L','-A',UA,u],capture_output=True).stdout
os.makedirs('stock',exist_ok=True);meta={};seen=set()
def save(data,name):
  im=Image.open(io.BytesIO(data)).convert('RGB');th=round(im.width/1.6)
  if th<im.height: t=(im.height-th)//2;im=im.crop((0,t,im.width,t+th))
  else:
    tw=round(im.height*1.6);l=(im.width-tw)//2;im=im.crop((l,0,l+tw,im.height))
  im.resize((1600,1000),Image.LANCZOS).save('stock/'+name+'.webp',quality=84)
for slug,(q1,q2) in Q.items():
  n=0
  try:
    h=get('https://stocksnap.io/search/'+urllib.parse.quote_plus(q1)).decode('utf8','ignore')
    for u in dict.fromkeys(re.findall(r'cdn\.stocksnap\.io/img-thumbs/[0-9a-z]+/([^"? ]+\.jpg)',h)):
      if u in seen: continue
      d=get('https://cdn.stocksnap.io/img-thumbs/960w/'+u)
      try: save(d,f'{slug}-{n+1}')
      except Exception: continue
      seen.add(u);n+=1;meta[f'{slug}-{n}']=['stocksnap',u]
      if n==3: break
  except Exception as e: print('fail ss',slug,e)
  try:
    h=get('https://burst.shopify.com/photos/search?q='+urllib.parse.quote_plus(q2)).decode('utf8','ignore');k=0
    for u in dict.fromkeys(re.findall(r'burst\.shopifycdn\.com/photos/([a-z0-9-]+\.jpg)',h)):
      if u in seen: continue
      d=get('https://burst.shopifycdn.com/photos/'+u+'?width=1600&format=pjpg')
      try: save(d,f'{slug}-{n+1}')
      except Exception: continue
      seen.add(u);n+=1;k+=1;meta[f'{slug}-{n}']=['burst',u]
      if k==3: break
  except Exception as e: print('fail burst',slug,e)
  print('got',slug,n)
json.dump(meta,open('stock/meta.json','w'),indent=1)
