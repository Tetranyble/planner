export default function Checkbox({ className = '', ...props }) {
    return <input {...props} type="checkbox" className={`size-4 rounded border-input bg-card text-primary accent-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background ${className}`} />;
}
