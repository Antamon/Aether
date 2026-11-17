/* Printable vector QR cards: millimetres throughout, no external service. */
window.AetherResearchQrPdf = (() => {
    let fontRequest;
    async function font() {
        if (!fontRequest) fontRequest = fetch('fonts/Amarante-Regular.ttf').then(async response => {
            if (!response.ok) throw new Error('Het lettertype voor de QR-kaarten kon niet geladen worden.');
            const bytes = new Uint8Array(await response.arrayBuffer());
            let binary = ''; for (let i=0;i<bytes.length;i+=8192) binary += String.fromCharCode(...bytes.subarray(i,i+8192));
            return btoa(binary);
        }).catch(error => {fontRequest=null;throw error;});
        return fontRequest;
    }
    function label(doc,text,x,y,maxWidth,size,maxLines) {
        const clean=String(text).normalize('NFC').replace(/\s+/g,' ').trim();
        let lines;
        for (let s=size;s>=7;s-=0.5) {
            doc.setFontSize(s);lines=doc.splitTextToSize(clean,maxWidth);
            if (lines.length<=maxLines) {doc.text(lines,x,y,{align:'center',lineHeightFactor:1.05});return;}
        }
        throw new Error('Een titel of vaardigheidsnaam is te lang voor een QR-kaart. Verkort deze tekst en probeer opnieuw.');
    }
    function stopwatch(doc,x,y) {
        doc.setFillColor(0);doc.circle(x,y,2.7,'F');
        doc.roundedRect(x-1.05,y-4,2.1,.65,.3,.3,'F');doc.rect(x-.4,y-3.6,.8,1.2,'F');
        doc.circle(x+2.15,y-2.3,.65,'F');doc.setDrawColor(255);doc.setLineWidth(.5);doc.setLineCap('round');doc.line(x,y-1.55,x,y);doc.setDrawColor(0);
    }
    async function create(challenge,skills) {
        if (!challenge.discoveries?.length) throw new Error('Deze challenge heeft nog geen discoveries om af te drukken.');
        const {jsPDF}=window.jspdf;
        const doc=new jsPDF({unit:'mm',format:'a4',compress:true});
        doc.addFileToVFS('AetherQr.ttf',await font());doc.addFont('AetherQr.ttf','AetherQr','normal');doc.setFont('AetherQr');
        doc.setProperties({title:`QR-codes - ${challenge.title}`,creator:'Aether'});
        const timed=challenge.discoveries.some(d=>(d.routes||[]).some(r=>(r.steps||[]).some(s=>(s.conditions||[]).some(c=>['duration','absolute'].includes(c.type)))));
        challenge.discoveries.forEach((d,i)=>{
            if (i && i%12===0) doc.addPage();
            const position=i%12,x=5.5+(position%3)*67,y=15.5+Math.floor(position/3)*67;
            doc.setFillColor(100,0,0);doc.rect(x,y,65,65,'F');
            doc.setFillColor(255);doc.roundedRect(x+2.5,y+2.5,60,60,4,4,'F');doc.setTextColor(0);
            label(doc,challenge.title.toLocaleUpperCase('nl'),x+32.5,y+10,54,14,2);
            const qr=qrcode(0,'M');qr.addData(`aether-research:${d.qrToken}`);qr.make();
            const count=qr.getModuleCount(),module=42/(count+8),left=x+11.5+4*module,top=y+14.5+4*module;
            doc.setFillColor(0);
            // Merge horizontal runs, retaining a four-module white quiet zone.
            for(let row=0;row<count;row++)for(let col=0;col<count;col++)if(qr.isDark(row,col)){
                const start=col;while(col+1<count&&qr.isDark(row,col+1))col++;
                doc.rect(left+start*module,top+row*module,(col-start+1)*module,module,'F');
            }
            const skill=skills.find(s=>Number(s.id)===Number(d.idSkill));
            label(doc,(skill?.name||'Onbekende vaardigheid').toLocaleUpperCase('nl'),x+32.5,y+58,43,12,1);
            if (timed) stopwatch(doc,x+57,y+57.5);
        });
        return doc;
    }
    async function download(challenge,skills) {
        const doc=await create(challenge,skills);
        const name=String(challenge.title).normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-zA-Z0-9_-]+/g,'-').replace(/^-|-$/g,'').slice(0,100)||'challenge';
        doc.save(`onderzoek-${name}-qr.pdf`);
    }
    return {create,download};
})();
