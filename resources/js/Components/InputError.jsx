export default function InputError({ message, className = '', ...props }) {
    return message ? <p {...props} role="alert" className={`text-sm text-destructive ${className}`}>{message}</p> : null;
}
