const assert=require('node:assert/strict');
const {estimateDeskew}=require('../../school/photo_tools.js');
const width=480,height=360;
function page(deg){const p=new Uint8ClampedArray(width*height*4).fill(255);const a=deg*Math.PI/180;for(let y=50;y<300;y+=24)for(let x=60;x<420;x++){if((x%35)>24)continue;for(let dy=0;dy<3;dy++){const nx=Math.round((x-width/2)*Math.cos(a)-(y+dy-height/2)*Math.sin(a)+width/2),ny=Math.round((x-width/2)*Math.sin(a)+(y+dy-height/2)*Math.cos(a)+height/2);if(nx>0&&nx<width&&ny>0&&ny<height){const i=(ny*width+nx)*4;p[i]=p[i+1]=p[i+2]=0;}}}return p;}
assert.equal(estimateDeskew(new Uint8ClampedArray(width*height*4).fill(255),width,height).angle,0,'Blank page must not rotate');
for(const tilt of [-5,4]){const r=estimateDeskew(page(tilt),width,height);assert.ok(Math.abs(r.angle+tilt)<=1,`Tilt ${tilt} should be corrected: ${r.angle}`);}
assert.equal(estimateDeskew(page(0),width,height).angle,0,'Straight page must stay straight');
console.log('Photo preparation: blank, straight and tilted text checks passed');
