// Definicije elemenata
const questionElement       = document.getElementById("question");
const answersContainer      = document.getElementById("answers");
const hintButton            = document.getElementById("hint-btn");
const hintElement           = document.getElementById("hint");
const nextButton            = document.getElementById("next-button");
const scoreElement          = document.getElementById("score-value");
const currentQuestionNum    = document.getElementById("current");
const totalQuestionsNum     = document.getElementById("total");
const questionImageContainer = document.getElementById("question-image-container");
const questionImage         = document.getElementById("question-image");

// Mafija zvučni efekti
const cashSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-coins-handling-1939.mp3');
const correctSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-slot-machine-win-1929.mp3');
const wrongSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3');

// Varijable za kviz
let questions             = [];
let currentQuestionIndex  = 0;
let score                 = 0;
let answered              = false;

// Učitavanje pitanja s index.php
async function loadQuestionsFromAPI() {
    try {
        // Dodaj efekt učitavanja
        questionElement.innerHTML = '<i class="fas fa-sync fa-spin"></i> Učitavanje pitanja...';
        
        const response = await fetch("index.php?getQuestions=1");
        
        if (!response.ok) {
            questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Greška pri dohvaćanju podataka!';
            return;
        }
        
        const data = await response.json();
        if (data && Array.isArray(data)) {
            questions = data;
            totalQuestionsNum.innerText = questions.length;
            loadQuestion();
        } else {
            questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Pogreška u formatu podataka!';
        }
    } catch (error) {
        questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Došlo je do greške!';
        console.error('Error loading questions:', error);
    }
}

// Ostatak JavaScript koda... 