import { Button } from '@/Components/ui/button';
export default function PrimaryButton({ className = '', disabled = false, children, ...props }) {
    return <Button {...props} className={className} disabled={disabled}>{children}</Button>;
}
