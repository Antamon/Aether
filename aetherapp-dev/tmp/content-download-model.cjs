const path=require('node:path'),{pathToFileURL}=require('node:url');
(async()=>{
 const runtime=path.join(process.env.TEMP,'aether-content-translation');
 const {pipeline,env}=await import(pathToFileURL(path.join(runtime,'node_modules/@huggingface/transformers/dist/transformers.node.mjs')).href);
 env.cacheDir=path.join(runtime,'models');env.allowLocalModels=false;
 const model=await pipeline('translation','Xenova/opus-mt-en-nl',{dtype:'q8',device:'cpu',session_options:{intraOpNumThreads:3,interOpNumThreads:1}});
 await model.dispose();console.log('English to Dutch model downloaded locally. No game text submitted.');process.exit(0);
})().catch(error=>{console.error(error);process.exit(1);});
