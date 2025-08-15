/**
 * Example Component Unit Test
 * 
 * Demonstrates testing patterns for React components
 * with accessibility, user interactions, and API mocking.
 * 
 * @package Testing
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React from 'react';
import { renderWithProviders, testAccessibility, mockApiResponses } from '../../test-utils';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// Mock component for demonstration
const LoginForm: React.FC = () => {
    const [email, setEmail] = React.useState('');
    const [password, setPassword] = React.useState('');
    const [error, setError] = React.useState('');
    const [loading, setLoading] = React.useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        if (!email) {
            setError('Email is required');
            setLoading(false);
            return;
        }

        if (!password) {
            setError('Password is required');
            setLoading(false);
            return;
        }

        // Mock API call
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            if (!response.ok) {
                throw new Error('Login failed');
            }

            // Handle success
            console.log('Login successful');
        } catch (err) {
            setError('Login failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} data-testid="login-form">
            <h1>Login</h1>

            <div>
                <label htmlFor="email">Email</label>
                <input
                    id="email"
                    type="email"
                    data-testid="email-input"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    aria-describedby="email-error"
                />
            </div>

            <div>
                <label htmlFor="password">Password</label>
                <input
                    id="password"
                    type="password"
                    data-testid="password-input"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    aria-describedby="password-error"
                />
            </div>

            {error && (
                <div
                    role="alert"
                    data-testid="error-message"
                    id="email-error password-error"
                >
                    {error}
                </div>
            )}

            <button
                type="submit"
                data-testid="login-button"
                disabled={loading}
                aria-describedby={error ? 'error-message' : undefined}
            >
                {loading ? 'Logging in...' : 'Login'}
            </button>
        </form>
    );
};

describe('LoginForm Component', () => {
    beforeEach(() => {
        // Clear any previous mocks
        jest.clearAllMocks();

        // Reset fetch mock
        (globalThis as any).fetch = jest.fn();
    });

    it('renders login form correctly', () => {
        const { container } = renderWithProviders(<LoginForm />);

        expect(screen.getByRole('heading', { name: /login/i })).toBeInTheDocument();
        expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /login/i })).toBeInTheDocument();
    });

    it('displays validation errors for empty fields', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        const submitButton = screen.getByRole('button', { name: /login/i });
        await user.click(submitButton);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Email is required');
        });
    });

    it('displays password error when email is provided but password is empty', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        const emailInput = screen.getByLabelText(/email/i);
        const submitButton = screen.getByRole('button', { name: /login/i });

        await user.type(emailInput, 'test@example.com');
        await user.click(submitButton);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Password is required');
        });
    });

    it('submits form with valid credentials', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        // Mock successful API response
        (globalThis.fetch as jest.Mock).mockResolvedValueOnce(
            mockApiResponses.success({ token: 'mock-token' })
        );

        const emailInput = screen.getByLabelText(/email/i);
        const passwordInput = screen.getByLabelText(/password/i);
        const submitButton = screen.getByRole('button', { name: /login/i });

        await user.type(emailInput, 'test@example.com');
        await user.type(passwordInput, 'password123');
        await user.click(submitButton);

        await waitFor(() => {
            expect(globalThis.fetch).toHaveBeenCalledWith('/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'test@example.com',
                    password: 'password123'
                })
            });
        });
    });

    it('displays error message on login failure', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        // Mock API error response
        (globalThis.fetch as jest.Mock).mockResolvedValueOnce(
            mockApiResponses.unauthorized()
        );

        const emailInput = screen.getByLabelText(/email/i);
        const passwordInput = screen.getByLabelText(/password/i);
        const submitButton = screen.getByRole('button', { name: /login/i });

        await user.type(emailInput, 'test@example.com');
        await user.type(passwordInput, 'wrongpassword');
        await user.click(submitButton);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Login failed. Please try again.');
        });
    });

    it('disables submit button while loading', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        // Mock slow API response
        (globalThis.fetch as jest.Mock).mockImplementation(
            () => new Promise(resolve => setTimeout(resolve, 100))
        );

        const emailInput = screen.getByLabelText(/email/i);
        const passwordInput = screen.getByLabelText(/password/i);
        const submitButton = screen.getByRole('button', { name: /login/i });

        await user.type(emailInput, 'test@example.com');
        await user.type(passwordInput, 'password123');
        await user.click(submitButton);

        // Button should be disabled and show loading text
        expect(submitButton).toBeDisabled();
        expect(submitButton).toHaveTextContent('Logging in...');
    });

    it('is accessible', async () => {
        const { container } = renderWithProviders(<LoginForm />);
        await testAccessibility(container);
    });

    it('supports keyboard navigation', async () => {
        const { user } = renderWithProviders(<LoginForm />);

        const emailInput = screen.getByLabelText(/email/i);
        const passwordInput = screen.getByLabelText(/password/i);
        const submitButton = screen.getByRole('button', { name: /login/i });

        // Tab through form elements
        await user.tab();
        expect(emailInput).toHaveFocus();

        await user.tab();
        expect(passwordInput).toHaveFocus();

        await user.tab();
        expect(submitButton).toHaveFocus();
    });

    it('has proper ARIA attributes', () => {
        renderWithProviders(<LoginForm />);

        const emailInput = screen.getByLabelText(/email/i);
        const passwordInput = screen.getByLabelText(/password/i);

        expect(emailInput).toHaveAttribute('aria-describedby', 'email-error');
        expect(passwordInput).toHaveAttribute('aria-describedby', 'password-error');
    });
});
