import { Button } from '@/Components/ui/button';
export default function SecondaryButton({ type = 'button', className = '', disabled = false, children, ...props }) {
    return <Button {...props} type={type} variant="outline" className={className} disabled={disabled}>{children}</Button>;
}
