const assert=require('node:assert/strict');
const qrcode=require('../js/vendor/qrcode-generator.js');
const jsQR=require('../js/vendor/jsQR.js');
const token='aether-research:'+'0123456789abcdef'.repeat(4);
const qr=qrcode(0,'M');qr.addData(token);qr.make();
const scale=8,border=4,size=(qr.getModuleCount()+border*2)*scale,pixels=new Uint8ClampedArray(size*size*4).fill(255);
for(let y=0;y<qr.getModuleCount();y++)for(let x=0;x<qr.getModuleCount();x++)if(qr.isDark(y,x)){
    for(let dy=0;dy<scale;dy++)for(let dx=0;dx<scale;dx++){
        const i=(((y+border)*scale+dy)*size+(x+border)*scale+dx)*4;pixels[i]=pixels[i+1]=pixels[i+2]=0;
    }
}
assert.equal(jsQR(pixels,size,size).data,token);
console.log('PASS: bundled QR generator and scanner round-trip the opaque discovery token.');
