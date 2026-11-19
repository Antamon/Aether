const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const codes=[],labels=[];
function Doc(){return new Proxy({splitTextToSize:text=>[text],text:lines=>labels.push(...lines)}, {get:(target,key)=>key==='then'?undefined:target[key]||(()=>{})});}
const window={jspdf:{jsPDF:Doc}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../js/researchQrPdf.js'),'utf8'),{window,Uint8Array,btoa:text=>Buffer.from(text,'binary').toString('base64'),fetch:async()=>({ok:true,arrayBuffer:async()=>new ArrayBuffer(0)}),qrcode:()=>({addData:text=>codes.push(text),make(){},getModuleCount:()=>1,isDark:()=>false})});
(async()=>{
    const challenge={title:'Challenge',discoveries:[{title:'Normal',idSkill:6,qrToken:'abc',routes:[]},{title:'Conclusion',isConclusion:1,idSkill:6,qrToken:null,routes:[]},{title:'Legacy conclusion',isConclusion:1,idSkill:6,qrToken:'must-not-print',routes:[]}]};
    await window.AetherResearchQrPdf.create(challenge,[{id:6,name:'Speuren'}]);
    assert.deepEqual(codes,['aether-research:abc']);assert(labels.includes('NORMAL'));assert(!labels.some(label=>label.includes('CONCLUSION')));
    assert.equal(challenge.discoveries.length,3,'Printing does not mutate the challenge');
    await assert.rejects(window.AetherResearchQrPdf.create({title:'Only conclusion',discoveries:challenge.discoveries.slice(1)},[]),/geen gewone discoveries/);
    console.log('PASS: conclusions never generate QR cards, including legacy tokens and conclusion-only challenges.');
})().catch(error=>{console.error(error);process.exitCode=1;});
