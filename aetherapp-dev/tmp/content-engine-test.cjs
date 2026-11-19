const path=require('node:path'),{pathToFileURL}=require('node:url');
(async()=>{
 const runtime=path.join(process.env.TEMP,'aether-content-translation');
 const {pipeline,env}=await import(pathToFileURL(path.join(runtime,'node_modules/@huggingface/transformers/dist/transformers.node.mjs')).href);
 env.cacheDir=path.join(runtime,'models');env.allowLocalModels=false;
 const translate=await pipeline('translation','Xenova/opus-mt-nl-en',{dtype:'q8',device:'cpu',session_options:{intraOpNumThreads:2,interOpNumThreads:1},progress_callback:p=>{if(p.status==='ready')console.log('Model ready');}});
 console.log(JSON.stringify(await translate(['Je bezit aandelen in één of meerdere bedrijven.','De deelnemers moeten de opdracht afronden voordat de volgende feedback beschikbaar wordt.','De directeur geeft het personeelslid een loonsverhoging.'],{max_new_tokens:120,num_beams:3})));
 await translate.dispose();
})().catch(e=>{console.error(e);process.exitCode=1;});
