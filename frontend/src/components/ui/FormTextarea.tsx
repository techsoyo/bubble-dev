import React from 'react';

export interface FormTextareaProps {
  label?: string;
  name: string;
  value: string;
  onChange: (e: React.ChangeEvent<HTMLTextAreaElement>) => void;
  error?: string;
  required?: boolean;
  placeholder?: string;
  rows?: number;
  className?: string;
}

export function FormTextarea({
  label,
  name,
  value,
  onChange,
  error,
  required,
  placeholder,
  rows = 4,
  className = ''
}: FormTextareaProps) {
  return (
    <div className={`form-group ${className}`}>
      {label && (
        <label htmlFor={name} className="form-label">
          {label} {required && <span className="text-red-500">*</span>}
        </label>
      )}
      <textarea
        id={name}
        name={name}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        rows={rows}
        className={`form-textarea ${error ? 'border-red-500' : ''}`}
        required={required}
      />
      {error && <span className="form-error text-red-500 text-sm">{error}</span>}
    </div>
  );
}
