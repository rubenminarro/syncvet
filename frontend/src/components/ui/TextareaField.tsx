import { useId, type Ref, type TextareaHTMLAttributes } from 'react';

interface TextareaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string;
  error?: string | undefined;
  hint?: string;
  ref?: Ref<HTMLTextAreaElement>;
}

/** Un texto largo (las notas) con su etiqueta y su error, como TextField. */
export function TextareaField({
  label,
  error,
  hint,
  id,
  className,
  ref,
  ...rest
}: TextareaFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;

  return (
    <div className="mb-3">
      <label className={`form-label${rest.required === true ? ' required' : ''}`} htmlFor={inputId}>
        {label}
      </label>
      <textarea
        ref={ref}
        id={inputId}
        className={`form-control${error ? ' is-invalid' : ''} ${className ?? ''}`.trim()}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        {...rest}
      />
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : !error && hint ? (
        <small className="form-hint">{hint}</small>
      ) : null}
    </div>
  );
}
