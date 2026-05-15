import { useEffect } from 'react';

interface ToastProps {
  message: string;
  type: 'error' | 'success';
  onDismiss: () => void;
}

export function Toast({ message, type, onDismiss }: ToastProps) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, 5000);
    return () => clearTimeout(timer);
  }, [onDismiss]);

  return (
    <div
      role="alert"
      className={`toast toast--${type}`}
      onClick={onDismiss}
    >
      <span className="toast__icon">{type === 'error' ? '✕' : '✓'}</span>
      <span className="toast__message">{message}</span>
    </div>
  );
}
