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
            console.error("Greška u odgovoru servera:", response.status, response.statusText);
            questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Greška pri učitavanju podataka!';
            return;
        }

        questions = await response.json();
        console.log("Dohvaćeni podaci (cijeli JSON):", questions);

        if (questions.error) {
            questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Greška: ' + questions.error;
            return;
        }

        // Debug: Ispiši sva pitanja
        questions.forEach((q, i) => {
            console.log(`[Debug] Pitanje #${i+1}:`, q);
        });

        // Postavi ukupan broj pitanja
        totalQuestionsNum.innerText = questions.length;
        
        loadQuestion();
    } catch (error) {
        questionElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Greška na serveru!';
        console.error("Pogreška prilikom dohvata pitanja:", error);
    }
}

// Učitavanje jednog pitanja
function loadQuestion() {
    resetState();
    const currentQuestion = questions[currentQuestionIndex];
    console.log(`[Debug] Učitavam pitanje indeks: ${currentQuestionIndex}`, currentQuestion);

    // Postavi broj trenutnog pitanja
    currentQuestionNum.innerText = currentQuestionIndex + 1;
    
    // Mafija efekt prije prikaza pitanja
    questionElement.style.opacity = 0;
    setTimeout(() => {
        // Postavi tekst pitanja
        questionElement.innerText = currentQuestion.question;
        questionElement.style.opacity = 1;
    }, 300);
    
    // Provjera postoji li slika i postavljanje njezinog prikaza
    if (currentQuestion.image && currentQuestion.image.trim() !== "") {
        questionImage.src = currentQuestion.image;
        questionImageContainer.style.display = "block";
    } else {
        questionImageContainer.style.display = "none";
    }

    // Obrada odgovora (odvojeni znakom "|")
    let possibleAnswers = currentQuestion.answers.split("|");
    let correctAnswerIndex = parseInt(currentQuestion.correctAnswer, 10);
    console.log(`[Debug] possibleAnswers=`, possibleAnswers);
    console.log(`[Debug] correctAnswerIndex=`, correctAnswerIndex);

    // Očisti prethodne odgovore i stvori nove gumbe
    answersContainer.innerHTML = '';
    
    possibleAnswers.forEach((answer, index) => {
        if (answer && answer.trim() !== '') {
            const button = document.createElement('button');
            button.className = 'answer-btn';
            button.innerText = answer;
            button.dataset.correct = (index === correctAnswerIndex) ? "true" : "false";
            button.addEventListener('click', selectAnswer);
            button.style.opacity = 0;
            answersContainer.appendChild(button);
            
            // Animiraj pojavljivanje gumba s odgodom
            setTimeout(() => {
                button.style.opacity = 1;
            }, 100 * (index + 1));
        }
    });

    // Postavi savjet
    hintElement.innerText = currentQuestion.hint || "Nema pomoći za ovo pitanje.";
    hintElement.style.display = "none";
    hintButton.innerHTML = '<i class="fas fa-lightbulb"></i> POMOĆ';
    
    // Pusti zvuk novca za novo pitanje
    cashSound.volume = 0.2;
    cashSound.play();
}

// Prekidač za prikaz/sakrivanje savjeta
function toggleHint() {
    if (hintElement.style.display === "none") {
        hintElement.style.display = "block";
        hintButton.innerHTML = '<i class="fas fa-eye-slash"></i> SAKRIJ';
    } else {
        hintElement.style.display = "none";
        hintButton.innerHTML = '<i class="fas fa-lightbulb"></i> POMOĆ';
    }
}

// Resetiranje stanja prije učitavanja novog pitanja
function resetState() {
    answered = false;
    nextButton.disabled = true;
    // Stilovi se resetiraju stvaranjem novih gumba u loadQuestion
}

// Funkcija koja se poziva kada korisnik klikne na odgovor
function selectAnswer(e) {
    if (answered) return;
    
    const selectedButton = e.target;
    const isCorrect = selectedButton.dataset.correct === "true";
    console.log("[Debug] Kliknuo si:", selectedButton.innerText, " -> isCorrect?", isCorrect);

    if (isCorrect) {
        selectedButton.classList.add("correct");
        score++;
        // Mafija efekat za točan odgovor
        selectedButton.innerHTML += ' <i class="fas fa-check"></i>';
        correctSound.volume = 0.3;
        correctSound.play();
        
        // Animacija novca padanje
        createMoneyAnimation();
    } else {
        selectedButton.classList.add("incorrect");
        selectedButton.innerHTML += ' <i class="fas fa-times"></i>';
        wrongSound.volume = 0.3;
        wrongSound.play();
        
        // Označi točan odgovor
        const buttons = document.querySelectorAll('.answer-btn');
        buttons.forEach(button => {
            if (button.dataset.correct === "true") {
                button.classList.add("correct");
                button.innerHTML += ' <i class="fas fa-check"></i>';
            }
        });
    }

    // Onemogući daljnje klikanje
    const buttons = document.querySelectorAll('.answer-btn');
    buttons.forEach(button => { button.disabled = true; });
    
    updateScoreDisplay();
    nextButton.disabled = false;
    answered = true;
}

// Funkcija za učitavanje sljedećeg pitanja
function nextQuestion() {
    currentQuestionIndex++;
    if (currentQuestionIndex < questions.length) {
        loadQuestion();
    } else {
        // Kviz završen s mafija stilom
        questionElement.innerHTML = `<span style="font-size: 2rem;"><i class="fas fa-crown"></i> KVIZ ZAVRŠEN!</span>`;
        questionImageContainer.style.display = "none";
        answersContainer.innerHTML = `
            <div style="text-align: center; padding: 20px; background: rgba(255,215,0,0.1); border-left: 4px solid #ffd700;">
                <p style="font-size: 1.5rem; margin-bottom: 15px; color: #ffd700;">
                    Konačni rezultat: <strong>${score}</strong> od <strong>${questions.length}</strong>
                </p>
                <p style="font-size: 1.2rem; color: #1e90ff;">
                    ${score === questions.length ? 
                        'Savršen rezultat! Pravi si mafija Šef!' : 
                        score > questions.length/2 ? 
                            'Dobar posao! Napredovao si u mafijaškoj hijerarhiji!' : 
                            'Imaš još posla... Mafija ne prašta pogreške!'}
                </p>
                <button onclick="window.location.reload()" style="margin-top: 20px; background-color: #1e90ff; color: #fff; padding: 10px 20px; border: none; cursor: pointer; font-weight: bold; text-transform: uppercase;">
                    <i class="fas fa-redo"></i> Igraj ponovno
                </button>
            </div>
        `;
        nextButton.style.display = "none";
        hintButton.style.display = "none";
        
        // Veliki efekt za završetak kviza
        if (score > questions.length/2) {
            createMoneyAnimation(50); // Puno novca za dobar rezultat
        }
    }
}

// Ažuriranje prikaza rezultata
function updateScoreDisplay() {
    scoreElement.innerText = score;
}

// Funkcija za animaciju padajućeg novca
function createMoneyAnimation(count = 15) {
    for (let i = 0; i < count; i++) {
        const money = document.createElement('div');
        money.innerHTML = '<i class="fas fa-dollar-sign"></i>';
        money.style.position = 'fixed';
        money.style.color = '#ffd700';
        money.style.fontSize = Math.random() * 20 + 10 + 'px';
        money.style.left = Math.random() * 100 + 'vw';
        money.style.top = '-20px';
        money.style.opacity = Math.random() * 0.7 + 0.3;
        money.style.zIndex = '1000';
        money.style.pointerEvents = 'none';
        document.body.appendChild(money);
        
        const duration = Math.random() * 3 + 2;
        money.style.transition = `top ${duration}s linear, transform ${duration}s linear`;
        
        setTimeout(() => {
            money.style.top = '110vh';
            money.style.transform = `rotate(${Math.random() * 360}deg)`;
        }, 10);
        
        setTimeout(() => {
            document.body.removeChild(money);
        }, duration * 1000);
    }
}

// Event listeneri
document.addEventListener("DOMContentLoaded", function() {
    loadQuestionsFromAPI();
    hintButton.addEventListener("click", toggleHint);
    nextButton.addEventListener("click", nextQuestion);
});
