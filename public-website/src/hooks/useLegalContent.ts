import { useEffect, useState } from 'react';
import { requestJson } from '../services/apiClient';
export type LegalContent={intro:string;sections:{title:string;body:string}[]};
export function useLegalContent(page:'terms'|'privacy') {
 const [content,setContent]=useState<LegalContent|null>(null);
 const [updated,setUpdated]=useState<string|null>(null);
 useEffect(()=>{let active=true;requestJson<{content:LegalContent;updated_at:string}|null>(`/settings/legal-content.php?page=${page}`).then(data=>{if(active&&data?.content){setContent(data.content);setUpdated(data.updated_at)}}).catch(()=>{});return()=>{active=false}},[page]);
 return {content,updated};
}
