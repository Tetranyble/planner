import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'h-10 rounded-md border border-input bg-card px-3 text-foreground placeholder:text-muted-foreground shadow-sm focus:border-ring focus:ring-2 focus:ring-ring/30 ' +
                className
            }
            ref={localRef}
        />
    );
});
