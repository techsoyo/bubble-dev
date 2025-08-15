#!/usr/bin/env python3
"""
Bubble of Talents - AI Testing Server para Codespaces
Servidor IA con modelo potente para testing de matching de CVs
"""

import os
import json
import time
import torch
import logging
from typing import Dict, List, Optional
from flask import Flask, request, jsonify
from transformers import AutoTokenizer, AutoModelForCausalLM, BitsAndBytesConfig
import re

# Configurar logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class AIMatchingServer:
    def __init__(self):
        self.app = Flask(__name__)
        self.model = None
        self.tokenizer = None
        self.model_name = None
        self.setup_routes()
        
    def load_model(self):
        """Cargar modelo optimizado según recursos disponibles"""
        logger.info("🔄 Inicializando modelo IA...")
        
        # Detectar recursos disponibles
        has_gpu = torch.cuda.is_available()
        if has_gpu:
            gpu_memory = torch.cuda.get_device_properties(0).total_memory / 1e9
            logger.info(f"GPU detectada: {torch.cuda.get_device_name(0)} ({gpu_memory:.1f}GB)")
        else:
            logger.info("CPU mode - sin GPU detectada")
        
        # Seleccionar modelo según recursos
        if has_gpu and gpu_memory >= 15:
            # GPU potente - modelo grande
            self.model_name = "microsoft/DialoGPT-large"
            quantization = None
        elif has_gpu:
            # GPU moderada - modelo cuantizado
            self.model_name = "microsoft/DialoGPT-medium"
            quantization = BitsAndBytesConfig(
                load_in_4bit=True,
                bnb_4bit_compute_dtype=torch.float16
            )
        else:
            # CPU - modelo pequeño y rápido
            self.model_name = "distilgpt2"
            quantization = None
        
        logger.info(f"Cargando modelo: {self.model_name}")
        
        try:
            self.tokenizer = AutoTokenizer.from_pretrained(self.model_name)
            if self.tokenizer.pad_token is None:
                self.tokenizer.pad_token = self.tokenizer.eos_token
                
            model_kwargs = {
                "device_map": "auto" if has_gpu else None,
                "torch_dtype": torch.float16 if has_gpu else torch.float32,
            }
            
            if quantization:
                model_kwargs["quantization_config"] = quantization
                
            self.model = AutoModelForCausalLM.from_pretrained(
                self.model_name, 
                **model_kwargs
            )
            
            logger.info("✅ Modelo cargado exitosamente")
            return True
            
        except Exception as e:
            logger.error(f"❌ Error cargando modelo: {e}")
            return False

    def generate_matching_score(self, candidate: Dict, job: Dict) -> Dict:
        """Generar score de matching usando IA"""
        if not self.model or not self.tokenizer:
            return self._fallback_score(candidate, job)
        
        # Construir prompt optimizado
        prompt = self._build_matching_prompt(candidate, job)
        
        try:
            # Tokenizar con límites seguros
            inputs = self.tokenizer(
                prompt, 
                return_tensors="pt", 
                truncation=True, 
                max_length=1024,
                padding=True
            )
            
            if torch.cuda.is_available():
                inputs = {k: v.to(self.model.device) for k, v in inputs.items()}
            
            # Generar respuesta
            with torch.no_grad():
                outputs = self.model.generate(
                    **inputs,
                    max_new_tokens=300,
                    temperature=0.1,
                    do_sample=True,
                    pad_token_id=self.tokenizer.eos_token_id,
                    repetition_penalty=1.1
                )
            
            # Decodificar respuesta
            response = self.tokenizer.decode(outputs[0], skip_special_tokens=True)
            response = response[len(prompt):].strip()
            
            # Extraer y validar JSON
            return self._parse_ai_response(response, candidate, job)
            
        except Exception as e:
            logger.error(f"Error en generación IA: {e}")
            return self._fallback_score(candidate, job)

    def _build_matching_prompt(self, candidate: Dict, job: Dict) -> str:
        """Construir prompt optimizado para matching"""
        skills_candidate = ", ".join(candidate.get('hard_skills', []))
        skills_required = ", ".join(job.get('required_skills', []))
        experience_count = len(candidate.get('experience', []))
        
        return f"""Analiza compatibilidad laboral y responde SOLO JSON válido:

CANDIDATO:
- Skills: {skills_candidate}
- Experiencia: {experience_count} posiciones
- Educación: {candidate.get('education', [{}])[0].get('title', 'No especificada')}

TRABAJO:
- Título: {job.get('title', 'No especificado')}
- Skills requeridas: {skills_required}
- Nivel: {job.get('level', 'No especificado')}

Respuesta JSON:
{{"overall_score": [0-100], "skills_match": [0-100], "experience_match": [0-100], "recommendation": "HIRE|INTERVIEW|REJECT", "explanation": "Resumen breve"}}"""

    def _parse_ai_response(self, response: str, candidate: Dict, job: Dict) -> Dict:
        """Parsear y validar respuesta de IA"""
        try:
            # Buscar JSON en la respuesta
            json_match = re.search(r'\{[^}]*"overall_score"[^}]*\}', response, re.DOTALL)
            if json_match:
                result = json.loads(json_match.group())
                
                # Validar campos obligatorios
                if all(key in result for key in ['overall_score', 'skills_match', 'experience_match']):
                    # Asegurar que los scores están en rango válido
                    for score_key in ['overall_score', 'skills_match', 'experience_match']:
                        result[score_key] = max(0, min(100, int(result.get(score_key, 0))))
                    
                    return result
        except:
            pass
        
        # Si falla el parsing, usar fallback
        return self._fallback_score(candidate, job)

    def _fallback_score(self, candidate: Dict, job: Dict) -> Dict:
        """Score algorítmico de fallback"""
        candidate_skills = set(skill.lower() for skill in candidate.get('hard_skills', []))
        required_skills = set(skill.lower() for skill in job.get('required_skills', []))
        
        if not required_skills:
            skills_score = 70
        else:
            skills_match = len(candidate_skills.intersection(required_skills))
            skills_score = min(100, int((skills_match / len(required_skills)) * 100))
        
        experience_score = min(100, len(candidate.get('experience', [])) * 25)
        overall_score = int((skills_score * 0.6) + (experience_score * 0.4))
        
        recommendation = "HIRE" if overall_score >= 80 else "INTERVIEW" if overall_score >= 60 else "REJECT"
        
        return {
            "overall_score": overall_score,
            "skills_match": skills_score,
            "experience_match": experience_score,
            "education_match": 75,
            "cultural_fit": 70,
            "recommendation": recommendation,
            "explanation": f"Score algorítmico: {overall_score}/100. Skills match: {skills_score}%",
            "source": "fallback_algorithm"
        }

    def setup_routes(self):
        """Configurar endpoints de la API"""
        
        @self.app.route('/health', methods=['GET'])
        def health():
            return jsonify({
                "status": "healthy",
                "model": self.model_name,
                "gpu_available": torch.cuda.is_available(),
                "gpu_name": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,
                "timestamp": time.time()
            })

        @self.app.route('/match', methods=['POST'])
        def match_single():
            try:
                data = request.json
                candidate = data.get('candidate', {})
                job = data.get('job', {})
                
                start_time = time.time()
                result = self.generate_matching_score(candidate, job)
                processing_time = round((time.time() - start_time) * 1000)
                
                return jsonify({
                    "success": True,
                    "data": result,
                    "meta": {
                        "model": self.model_name,
                        "processing_time_ms": processing_time,
                        "gpu_used": torch.cuda.is_available()
                    }
                })
            except Exception as e:
                return jsonify({
                    "success": False,
                    "error": str(e)
                }), 500

        @self.app.route('/batch-match', methods=['POST'])
        def match_batch():
            try:
                data = request.json
                candidates = data.get('candidates', [])
                job = data.get('job', {})
                
                results = []
                start_time = time.time()
                
                for i, candidate in enumerate(candidates):
                    match_result = self.generate_matching_score(candidate, job)
                    results.append({
                        "candidate_index": i,
                        "candidate_id": candidate.get('id'),
                        "matching": match_result
                    })
                
                # Ordenar por overall_score descendente
                results.sort(key=lambda x: x['matching']['overall_score'], reverse=True)
                
                processing_time = round((time.time() - start_time) * 1000)
                
                return jsonify({
                    "success": True,
                    "data": {
                        "ranked_candidates": results,
                        "total_processed": len(candidates)
                    },
                    "meta": {
                        "model": self.model_name,
                        "processing_time_ms": processing_time,
                        "avg_time_per_candidate": round(processing_time / len(candidates)) if candidates else 0
                    }
                })
            except Exception as e:
                return jsonify({
                    "success": False,
                    "error": str(e)
                }), 500

    def run(self, host='0.0.0.0', port=5000, debug=False):
        """Iniciar servidor"""
        logger.info("🚀 Iniciando Bubble of Talents AI Server...")
        
        if not self.load_model():
            logger.error("❌ No se pudo cargar el modelo - usando solo fallback")
        
        logger.info(f"🌐 Servidor iniciando en http://{host}:{port}")
        self.app.run(host=host, port=port, debug=debug)

if __name__ == "__main__":
    server = AIMatchingServer()
    server.run()
