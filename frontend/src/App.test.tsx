import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';


// Mock del módulo api para evitar error de import.meta.env
jest.mock('./lib/api', () => ({
    api: { create: jest.fn() }
}));

// Mock de AOS
jest.mock('aos', () => ({
    init: jest.fn(),
}));

import App from './App';

describe('App', () => {
    it('renders without crashing', () => {
        render(<App />);
        expect(screen.getByText(/sube tu curriculum vitae/i)).toBeInTheDocument();
    });
});
