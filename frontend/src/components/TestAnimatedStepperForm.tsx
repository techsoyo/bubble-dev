
import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import DOMPurify from "dompurify";

// Definir colores internamente en lugar de importarlos
const COLORS = {
  primary: "#5A3DE1",
  secondary: "#FF4785",
  background: "#F9F9F9",
  surface: "#FFFFFF",
  text: "#2F2F2F",
  placeholder: "#757575",
  disabled: "#CCCCCC",
  error: "#FF5252",
  success: "#4CAF50",
  warning: "#FFC107",
  info: "#2196F3",
  darkGray: "#333333"
};

type Answer = {
  question: string;
  answer: string;
};

const BLOCK_WIDTH = 360;
const BLOCK_HEIGHT = 56;
const BLOCK_GAP_X = 24;
const BLOCK_GAP_Y = 32;

const questions = [
  { label: "Nombre completo", type: "text", validate: (v: string) => v.length > 2 },
  { label: "Correo electrónico", type: "email", validate: (v: string) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) },
  { label: "Teléfono (incluye código de país)", type: "text", validate: (v: string) => /^[\d\s\-\+\(\)]{7,20}$/.test(v) },
  // ...añade más preguntas si quieres
];

const BLOCK_H = 56;

function getGridPos(idx: number) {
  const col = idx % 3;
  const row = Math.floor(idx / 3);
  return {
    left: `${col * (BLOCK_WIDTH + BLOCK_GAP_X)}px`,
    top: `${40 + row * (BLOCK_HEIGHT + BLOCK_GAP_Y)}px`
  };
}

// Sanitiza cualquier entrada de texto
const sanitizeInput = (value: string) => DOMPurify.sanitize(value.trim());

export default function Stepper() {
  const [step, setStep] = useState(0);
  const [answers, setAnswers] = useState<Answer[]>([]);
  const [error, setError] = useState<string | null>(null);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    // Si estamos fuera del rango de preguntas, no hacemos nada
    if (step >= questions.length) {
      return;
    }

    const form = e.target as HTMLFormElement;
    const value = sanitizeInput((form.elements.namedItem("answer") as HTMLInputElement).value);

    // Validación por tipo de pregunta
    const currentQuestion = questions[step];
    // TypeScript assertion para evitar warnings
    if (!currentQuestion!.validate(value)) {
      setError("Dato inválido. Revisa el campo.");
      return;
    }

    setError(null);
    setAnswers([...answers, {
      question: currentQuestion!.label,
      answer: value
    }]);
    setStep(step + 1);
    form.reset();
  }

  return (
    <div className="relative min-h-screen w-full overflow-hidden">
      {/* BLOQUES FIJOS EN LA CUADRÍCULA (arriba, alineados izquierda) */}
      <AnimatePresence>
        {answers.map((ans, idx) => {
          const gridPos = getGridPos(idx);
          return (
            <motion.div
              key={ans.question + idx}
              className="absolute"
              initial={{
                top: "50%",
                left: "50%",
                x: "-50%",
                y: "0%",
                opacity: 0
              }}
              animate={{
                top: gridPos.top,
                left: gridPos.left,
                x: "0%",
                y: "0%",
                opacity: 1
              }}
              exit={{ opacity: 0 }}
              transition={{ type: "spring", stiffness: 42, damping: 13 }}
              style={{
                width: "min(90vw, 360px)",
                minWidth: "220px",
                maxWidth: "360px",
                height: BLOCK_H
              }}
            >
              <PreguntaRespuestaBlock
                pregunta={ans.question}
                respuesta={ans.answer}
                colorSecundario={COLORS.secondary}
                colorPrimary={COLORS.primary}
                colorDark={COLORS.darkGray}
                fixed
              />
            </motion.div>
          );
        })}
      </AnimatePresence>

      {/* BLOQUE ACTIVO (entra desde el centro de abajo) */}
      <AnimatePresence>
        {step < questions.length && (
          <motion.form
            key={`form-step-${step}`}
            className="fixed left-1/2 top-1/2 z-20"
            style={{
              width: "min(90vw, 360px)",
              minWidth: "220px",
              maxWidth: "360px",
              height: BLOCK_H,
              transform: "translate(-50%, -50%)"
            }}
            initial={{
              y: "50vh",
              opacity: 0
            }}
            animate={{
              y: 0,
              opacity: 1
            }}
            exit={{
              opacity: 0,
              y: "-16vh"
            }}
            transition={{ type: "spring", stiffness: 42, damping: 10 }}
            onSubmit={handleSubmit}
            autoComplete="off"
            noValidate
          >
            <PreguntaRespuestaBlock
              pregunta={step < questions.length ? questions[step]!.label : ""}
              respuesta=""
              colorSecundario={COLORS.secondary}
              colorPrimary={COLORS.primary}
              colorDark={COLORS.darkGray}
              inputName="answer"
            />
            <button
              className="text-[1rem] mt-6 px-6 py-2 bg-pink-400 rounded-full font-bold text-white shadow"
              type="submit"
            >
              Siguiente
            </button>
            {error && <div className="error mt-2">{error}</div>}
          </motion.form>
        )}
      </AnimatePresence>
      {/*
        Sugerencias de mejora:
        - Internacionalizar mensajes de error.
        - Validar en backend si se conecta a una API real.
        - Añadir reCAPTCHA si se expone a usuarios externos.
      */}
    </div>
  );
}

// Bloque visual
function PreguntaRespuestaBlock({
  pregunta,
  respuesta,
  colorSecundario,
  colorPrimary,
  colorDark,
  fixed,
  inputName
}: {
  pregunta: string;
  respuesta: string;
  colorSecundario: string;
  colorPrimary: string;
  colorDark: string;
  fixed?: boolean;
  inputName?: string;
}) {
  return (
    <div
      className="flex items-center overflow-hidden shadow rounded-full w-full h-full"
      style={{
        fontFamily: "poppins",
        minHeight: "48px"
      }}
    >
      <div
        className="flex-1 h-full flex items-center justify-center font-bold text-base"
        style={{
          background: colorSecundario,
          color: colorPrimary,
          fontWeight: 700,
          fontSize: "1.06rem"
        }}
      >
        {pregunta}
      </div>
      <div
        className="flex-1 h-full flex items-center justify-center bg-white font-semibold text-base"
        style={{
          color: colorDark,
          fontSize: "1.06rem"
        }}
      >
        {fixed ? (
          <span>{respuesta}</span>
        ) : (
          <input
            className="w-4/5 text-center border-0 border-b-2 focus:border-pink-400 focus:outline-none bg-transparent font-semibold text-base"
            style={{
              color: colorDark,
              fontSize: "1.06rem",
              fontWeight: 500
            }}
            type="text"
            name={inputName}
            required
            autoFocus
            autoComplete="off"
          />
        )}
      </div>
    </div>
  );
}
