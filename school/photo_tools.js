/* Local-only deskew estimate. Never uploads pixels or reads personal metadata. */
(function(root){
 'use strict';
 function estimateDeskew(pixels,width,height){
  const points=[];for(let y=0;y<height;y+=2)for(let x=0;x<width;x+=2){const i=(y*width+x)*4;if(pixels[i]*.299+pixels[i+1]*.587+pixels[i+2]*.114<120)points.push([x-width/2,y-height/2]);}
  if(points.length<100||points.length>width*height*.18)return {angle:0,confidence:0};
  const size=Math.ceil(Math.hypot(width,height))+10,offset=Math.ceil(size/2);let best=0,base=0,angle=0;
  for(let a=-8;a<=8;a+=.5){const rad=a*Math.PI/180,sin=Math.sin(rad),cos=Math.cos(rad),bins=new Uint32Array(size);for(const [x,y] of points){const row=Math.round(x*sin+y*cos)+offset;if(row>=0&&row<size)bins[row]++;}let score=0;for(const n of bins)score+=n*n;if(a===0)base=score;if(score>best){best=score;angle=a;}}
  const confidence=base?best/base:0;return confidence>=1.12&&Math.abs(angle)>=.5?{angle,confidence}:{angle:0,confidence};
 }
 const api={estimateDeskew};if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.SchoolPhotoTools=api;
})(typeof window!=='undefined'?window:globalThis);
