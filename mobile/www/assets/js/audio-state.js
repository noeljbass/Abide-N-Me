export const initialAudioState=Object.freeze({status:'idle',position:0,duration:0,error:null});
export function reduceAudioState(state,event){
  switch(event.type){
    case 'LOAD':return {...initialAudioState,status:'loading'};
    case 'READY':return {...state,status:'ready',duration:validTime(event.duration),error:null};
    case 'PLAY':return {...state,status:'playing',error:null};
    case 'PAUSE':return {...state,status:'paused',position:validTime(event.position)};
    case 'TIME':return {...state,position:validTime(event.position),duration:validTime(event.duration||state.duration)};
    case 'BUFFER':return {...state,status:'buffering'};
    case 'ENDED':return {...state,status:'ended',position:state.duration};
    case 'UNAVAILABLE':return {...initialAudioState,status:'unavailable',error:event.message||'Audio is unavailable.'};
    case 'ERROR':return {...state,status:'error',error:event.message||'Audio could not be played.'};
    case 'RESET':return {...initialAudioState};
    default:return state;
  }
}
function validTime(value){const number=Number(value);return Number.isFinite(number)&&number>=0?number:0;}
