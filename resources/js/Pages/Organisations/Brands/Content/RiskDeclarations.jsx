/** Explicit declarations are editorial metadata, not automated AI detection. */
const values = [{value:'unknown',label:'Not assessed (strictest matching rules apply)'},{value:'yes',label:'Yes'},{value:'no',label:'No'}];
export default function RiskDeclarations({form}) {
    return <fieldset className="space-y-3 rounded-lg border border-border p-3">
        <legend className="px-1 text-sm font-medium">Content risk declarations</legend>
        {[['ai_generated','Contains AI-generated material'],['includes_sensitive_claim','Includes sensitive claims']].map(([name,label])=><label key={name} className="block text-sm">
            {label}
            <select className="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" value={form.data[name]===null?'unknown':form.data[name]?'yes':'no'}
                onChange={e=>form.setData(name,e.target.value==='unknown'?null:e.target.value==='yes')}>
                {values.map(option=><option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
            {form.errors[name]&&<span className="text-xs text-destructive" role="alert">{form.errors[name]}</span>}
        </label>)}
        <p className="text-xs text-muted-foreground">Declarations are recorded in the immutable version hash. Unknown does not mean No.</p>
    </fieldset>;
}
