import React from 'react';

export interface FormInputProps {
  label?: string;
  name: string;
  type?: string;
  value: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  error?: string;
  required?: boolean;
  placeholder?: string;
  className?: string;
}

export function FormInput({
  label,
  name,
  type = 'text',
  value,
  onChange,
  error,
  required,
  placeholder,
  className = ''
}: FormInputProps) {
  return (
    <div className={`form-group ${className}`}>
      {label && (
        <label htmlFor={name} className="form-label">
          {label} {required && <span className="text-red-500">*</span>}
        </label>
      )}
      <input
        id={name}
        name={name}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        className={`form-input ${error ? 'border-red-500' : ''}`}
        required={required}
      />
      {error && <span className="form-error text-red-500 text-sm">{error}</span>}
    </div>
  );
}
