<?php
session_start();

/**
 * Ako ?getQuestions=1 postoji u URL-u, vraćamo JSON s pitanjima.
 * Inače se učitava normalna HTML stranica kviza.
 */

// Ako još nismo postavili vrijeme početka kviza u sesiju, postavi ga sada.
if (!isset($_SESSION['quiz_start_time'])) {
    $_SESSION['quiz_start_time'] = date("Y-m-d H:i:s");
}

if (isset($_GET['getQuestions']) && $_GET['getQuestions'] == 1) {
    // ========== KORAK 1: Uključi datoteku za konekciju s bazom ==========

    require_once 'db_connection.php';  // Uključivanje db_connection.php

    // ========== KORAK 2: Dohvati pitanja iz baze ==========

    // Check if ?tema=... ili iz session
    $tema = '';
    if (isset($_GET['tema']) && trim($_GET['tema']) !== '') {
        $tema = trim($_GET['tema']);
    } elseif (isset($_SESSION['temaID']) && trim($_SESSION['temaID']) !== '') {
        $tema = trim($_SESSION['temaID']);
    }

    if ($tema !== '') {
        $stmt = $conn->prepare("
            SELECT ID, tekst_pitanja, hint, slika
            FROM ep_pitanje
            WHERE aktivno = 1 AND temaID = :tema
            ORDER BY ID
        ");
        $stmt->execute([':tema' => $tema]);
    } else {
        // Ako nema teme, vratimo sva aktivna pitanja (ili prazno, prema želji).
        $stmt = $conn->query("
            SELECT ID, tekst_pitanja, hint, slika
            FROM ep_pitanje
            WHERE aktivno = 1
            ORDER BY ID
        ");
    }
    $questionsData = $stmt->fetchAll();

    // ========== KORAK 3: Dohvati odgovore i pripremi JSON ==========

    $questions = [];
    foreach ($questionsData as $q) {
        $questionId = $q['ID'];

        // Dohvati odgovore
        $stmtAnswers = $conn->prepare("
            SELECT tekst, tocno
            FROM ep_odgovori
            WHERE pitanjeID = :qid AND aktivno = 1
            ORDER BY ID
        ");
        $stmtAnswers->execute([':qid' => $questionId]);
        $answersRows = $stmtAnswers->fetchAll();

        $answers = [];
        $correctAnswerIndex = null;
        foreach ($answersRows as $index => $row) {
            $answers[] = $row['tekst'];
            if ($row['tocno'] == 1) {
                $correctAnswerIndex = $index;
            }
        }

        // Ako nema točnog odgovora, neka bude -1
        if ($correctAnswerIndex === null) {
            $correctAnswerIndex = -1;
        }

        $answersPipe = implode('|', $answers);

        $questions[] = [
            'question'      => $q['tekst_pitanja'],
            'answers'       => $answersPipe,
            'correctAnswer' => (string)$correctAnswerIndex,
            'image'         => $q['slika'] ?? ''
        ];
    }

    // ========== KORAK 4: Ispiši JSON i prekini ==========

    header('Content-Type: application/json');
    echo json_encode($questions);
    exit;
}

// ============== HTML stranica kviza ==============

// Ako je korisnik poslao temu (tema_id), spremi je u sesiju
if (isset($_POST['tema_id'])) {
    $_SESSION['temaID'] = $_POST['tema_id'];
}

// Ako nema teme u sesiji, vrati na odabir_teme.php
if (!isset($_SESSION['temaID'])) {
    header("Location: odabir_teme.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
  <meta charset="UTF-8">
  <title>Tehnička škola Čakovec Kviz | Ispitaj svoje znanje</title>
  <style>
    /* ---- OVDJE IDU SVE TVOJE STILSKE POSTAVKE ---- */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Roboto', sans-serif;
    }
    body {
        background: #1a1a24; /* Tamno plava pozadina */
        color: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(220,53,69,0.03)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(73,80,87,0.03)">TŠČ</text></svg>');
    }
    .quiz-container {
        width: 90%;
        max-width: 1200px;
        margin: 20px;
        padding: 30px;
        background: linear-gradient(145deg, #2a2a38, #12121a); /* Tamnoplavi gradijent */
        border: 2px solid #dc3545; /* Crvena granica */
        box-shadow: 0 0 20px rgba(220, 53, 69, 0.2), 0 0 60px rgba(220, 53, 69, 0.1);
        border-radius: 0; /* Oštre ravne linije za tehnički stil */
        display: flex;
        flex-direction: column;
        min-height: 80vh;
        position: relative;
        overflow: hidden;
    }
    #question-number {
        color: #dc3545;
        background: rgba(40, 40, 50, 0.7);
        border: 1px solid #dc3545;
        border-radius: 0;
        padding: 10px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 1.8rem;
        font-weight: bold;
        text-shadow: 0 0 5px #dc3545;
        font-family: 'Roboto', sans-serif;
        letter-spacing: 1px;
        border-bottom: 2px solid #6c757d; /* Siva linija ispod */
    }
    .question-hint-container {
        display: flex;
        width: 100%;
        margin-bottom: 20px;
        align-items: flex-start;
    }
    .question-box {
        background-color: #282832;
        padding: 25px;
        border-radius: 0;
        margin-right: 20px;
        flex: 3;
        border-left: 4px solid #dc3545; /* Crvena lijeva granica */
        box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
    }
    .question-box h2 {
        font-size: 2rem;
        color: #6c757d; /* Siva boja teksta */
        text-shadow: 0 0 8px rgba(73, 80, 87, 0.5);
        font-family: 'Roboto', sans-serif;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .hint-box {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: flex-start;
        margin-top: 0;
    }
    #hint-btn {
        background: #6c757d; /* Siva */
        color: #fff;
        padding: 14px 28px;
        border: none;
        border-radius: 0;
        cursor: pointer;
        font-size: 1.1rem;
        margin-bottom: 10px;
        transition: 0.3s ease;
        box-shadow: 0 0 5px #6c757d, 0 0 10px #6c757d;
        text-transform: uppercase;
        font-weight: bold;
        letter-spacing: 1px;
    }
    #hint-btn:hover {
        background: #5a6268;
        box-shadow: 0 0 10px #6c757d, 0 0 20px #6c757d;
    }
    #hint {
        font-style: italic;
        color: #dc3545; /* Crvena boja za hint */
        display: none;
        font-size: 1.1rem;
        margin-top: 5px;
        background-color: rgba(40, 40, 50, 0.8);
        padding: 15px;
        border-radius: 0;
        box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);
        border-left: 2px solid #dc3545;
    }
    .answers-box {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* 2x2 raspored */
        gap: 15px; 
        min-height: 250px; 
        margin-top: 20px;
    }
    .answer-btn {
        background-color: #282832;
        color: #fff;
        border: 2px solid #6c757d; /* Siva granica */
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1.2rem;
        position: relative;
        box-shadow: inset 0 0 8px rgba(73, 80, 87, 0.2);
        padding: 15px;
        border-radius: 0;
        font-family: 'Roboto', sans-serif;
    }
    .answer-btn:hover {
        background-color: #333340;
        color: #dc3545; /* Crvena boja teksta na hover */
        box-shadow: inset 0 0 15px rgba(220, 53, 69, 0.3);
        border-color: #dc3545;
    }
    #next-button {
        background-color: #6c757d;
        color: #fff;
        padding: 14px 28px;
        border: none;
        border-radius: 0;
        cursor: pointer;
        font-size: 1.1rem;
        margin-top: 20px;
        align-self: center; /* Centriraj dugme */
        box-shadow: 0 0 5px #6c757d, 0 0 10px #6c757d;
        transition: 0.3s ease;
        text-transform: uppercase;
        font-weight: bold;
        letter-spacing: 2px;
    }
    #next-button:hover {
        background-color: #dc3545; /* Crvena boja na hover */
        box-shadow: 0 0 10px #dc3545, 0 0 20px #dc3545;
        color: #fff;
    }
    .correct {
        background-color: #dc3545 !important; /* Crvena za točan */
        color: #fff !important;
        box-shadow: 0 0 15px #dc3545;
    }
    .incorrect {
        background-color: #444450 !important; /* Tamna za netočan */
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        opacity: 0.8;
    }
    #question-image {
        max-width: 100%;
        max-height: 300px;
        width: auto;
        height: auto;
        object-fit: contain;
        cursor: pointer;
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        padding-top: 60px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.9);
    }
    .modal-content {
        margin: auto;
        display: block;
        max-width: 90%;
        max-height: 90%;
    }
    #caption {
        margin: auto;
        display: block;
        width: 80%;
        max-width: 700px;
        text-align: center;
        color: #ccc;
        padding: 10px 0;
    }
    .close {
        position: absolute;
        top: 30px;
        right: 35px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover,
    .close:focus {
        color: #bbb;
        text-decoration: none;
    }
    .modal-content {
        margin: auto;
    }
    .close {
        position: absolute;
        top: 10px;
        right: 10px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
    }
    .close:hover, .close:focus {
        color: #bbb;
    }
    @media (max-width: 768px) {
        .quiz-container {
            width: 95%;
            padding: 20px;
        }
        .question-hint-container {
            flex-direction: column;
        }
        .question-box {
            margin-right: 0;
            margin-bottom: 20px;
        }
        .answers-box {
            grid-template-columns: 1fr; /* Pitanja idu jedno ispod drugog */
        }
        .answer-btn {
            width: 100%;
        }
    }
  </style>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
  <div class="quiz-container">
    <div id="question-number">
      <i class="fas fa-cogs" style="margin-right: 10px; color: #dc3545;"></i>Pitanje <span id="current">1</span> / <span id="total">10</span><i class="fas fa-cogs" style="margin-left: 10px; color: #dc3545;"></i>
    </div>
    <div class="question-hint-container">
      <div class="question-box">
        <h2 id="question">Učitavanje pitanja...</h2>
        <div id="question-image-container" style="margin-top: 15px; text-align: center; display: none;">
          <img id="question-image" style="max-width: 100%; max-height: 300px; border: 2px solid #dc3545;" />
        </div>
      </div>
      <div class="hint-box">
        <button id="hint-btn"><i class="fas fa-lightbulb" style="margin-right: 5px;"></i> POMOĆ</button>
        <div id="hint">Pomoć će se prikazati ovdje...</div>
      </div>
    </div>
    <div class="answers-box" id="answers">
      <!-- Odgovori će biti učitani dinamički -->
    </div>
    <button id="next-button" disabled><i class="fas fa-chevron-right" style="margin-right: 5px;"></i>SLJEDEĆE</button>
    
    <!-- Tehnička dekoracija -->
    <div style="position: absolute; bottom: 10px; right: 10px; opacity: 0.1; font-size: 40px; color: #dc3545;">
      <i class="fas fa-microchip"></i>
    </div>
    <div style="position: absolute; top: 10px; left: 10px; opacity: 0.1; font-size: 40px; color: #6c757d;">
      <i class="fas fa-cog"></i>
    </div>
  </div>

  <!-- Modal za uvećanu sliku -->
  <div id="imageModal" class="modal">
    <span class="close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="caption"></div>
  </div>

  <script>
    const questionElement        = document.getElementById("question");
    const answersContainer       = document.getElementById("answers");
    const hintButton             = document.getElementById("hint-btn");
    const hintElement            = document.getElementById("hint");
    const nextButton             = document.getElementById("next-button");
    const currentQuestionNum     = document.getElementById("current");
    const totalQuestionsNum      = document.getElementById("total");
    const questionImageContainer = document.getElementById("question-image-container");
    const questionImage          = document.getElementById("question-image");

    // Modal za sliku
    const modal      = document.getElementById("imageModal");
    const modalImage = document.getElementById("modalImage");
    const captionText= document.getElementById("caption");
    const closeSpan  = document.getElementsByClassName("close")[0];

    // Tehnički zvučni efekti
    const clickSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-modern-click-box-check-1120.mp3');
    const correctSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3');
    const wrongSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3');

    let questions = [];
    let currentQuestionIndex = 0;
    let userAnswers = [];
    let score = 0;
    let answered = false;

    // Učitavanje pitanja
    async function loadQuestionsFromDB() {
      try {
        // Dodaj efekt učitavanja
        questionElement.innerHTML = '<i class="fas fa-sync fa-spin"></i> Učitavanje pitanja...';
        
        // fetch("index.php?getQuestions=1")
        // Ako ima query string (npr. ?tema=2), zadrži ga i dodaj &getQuestions=1
        let url = "index.php?getQuestions=1";
        const queryString = window.location.search;
        if (queryString && !queryString.includes('getQuestions')) {
          url = "index.php" + queryString + "&getQuestions=1";
        }
        
        const response = await fetch(url);
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

    // Učitavanje pojedinačnog pitanja
    function loadQuestion() {
      resetState();
      
      if (currentQuestionIndex >= questions.length) {
        endQuiz();
        return;
      }
      
      const currentQuestion = questions[currentQuestionIndex];
      
      // Postavi broj trenutnog pitanja
      currentQuestionNum.innerText = currentQuestionIndex + 1;
      
      // Tehnički efekt prije prikaza pitanja  
      questionElement.style.opacity = 0;
      setTimeout(() => {
        questionElement.innerText = currentQuestion.question;
        questionElement.style.opacity = 1;
      }, 300);
      
      // Provjeri postoji li slika uz pitanje
      if (currentQuestion.image && currentQuestion.image.trim() !== "") {
        questionImage.src = currentQuestion.image;
        questionImageContainer.style.display = "block";
        
        // Dodaj event listener za klik na sliku (za uvećanje)
        questionImage.onclick = function() {
          modal.style.display = "block";
          modalImage.src = this.src;
          captionText.innerHTML = currentQuestion.question;
        }
        
        // Event listener za zatvaranje modala
        closeSpan.onclick = function() {
          modal.style.display = "none";
        }
      } else {
        questionImageContainer.style.display = "none";
      }
      
      // Pripremanje odgovora - razdvajanje pipe-odvojenog stringa
      const answersArray = currentQuestion.answers.split('|');
      
      // Očisti prethodne odgovore i dodaj nove
      answersContainer.innerHTML = '';
      
      // Dodaj gumbe s odgovorima
      answersArray.forEach((answer, index) => {
        if (answer && answer.trim() !== '') {
          const button = document.createElement('button');
          button.className = 'answer-btn';
          button.innerText = answer;
          button.dataset.index = index;
          button.dataset.correct = (index == currentQuestion.correctAnswer);
          button.addEventListener('click', selectAnswer);
          button.style.opacity = 0;
          answersContainer.appendChild(button);
          
          // Animiraj pojavljivanje gumba
          setTimeout(() => {
            button.style.opacity = 1;
          }, 100 * (index + 1));
        }
      });
      
      // Postavi savjet/hint
      if (currentQuestion.hint) {
        hintElement.innerText = currentQuestion.hint;
      } else {
        hintElement.innerText = "Nema dostupnog savjeta za ovo pitanje.";
      }
      
      // Pusti zvuk klika za novo pitanje
      clickSound.volume = 0.2;
      clickSound.play();
    }

    // Funkcija za odabir odgovora
    function selectAnswer(e) {
      const selectedButton = e.target;
      const isCorrect = selectedButton.dataset.correct === "true";
      
      // Resetiraj stil svih gumba
      const allButtons = document.querySelectorAll('.answer-btn');
      allButtons.forEach(button => {
        button.style.backgroundColor = "";
        button.style.color = "";
        button.style.borderColor = "";
        button.disabled = false;
      });
      
      // Označi samo odabrani odgovor
      selectedButton.style.backgroundColor = "#6c757d";
      selectedButton.style.color = "#fff";
      selectedButton.style.borderColor = "#6c757d";
      
      // Spremi odgovor korisnika
      userAnswers[currentQuestionIndex] = {
        questionIndex: currentQuestionIndex,
        selectedAnswer: selectedButton.dataset.index,
        isCorrect: isCorrect
      };
      
      // Povećaj score ako je odgovor točan
      if (isCorrect) {
        score++;
      }
      
      // Omogući gumb za sljedeće pitanje
      nextButton.disabled = false;
      
      // Pusti zvučni efekt za odabir
      clickSound.volume = 0.3;
      clickSound.play();
    }

    // Resetiranje stanja za novo pitanje
    function resetState() {
      nextButton.disabled = true;
      hintElement.style.display = "none";
      hintButton.innerHTML = '<i class="fas fa-lightbulb"></i> POMOĆ';
      
      // Resetiraj stil svih gumba ako postoje
      const allButtons = document.querySelectorAll('.answer-btn');
      allButtons.forEach(button => {
        button.style.backgroundColor = "";
        button.style.color = "";
        button.style.borderColor = "";
        button.disabled = false;
      });
    }

    // Funkcija za sljedeće pitanje
    function nextQuestion() {
      currentQuestionIndex++;
      
      if (currentQuestionIndex < questions.length) {
        loadQuestion();
      } else {
        endQuiz();
      }
    }

    // Funkcija koja se poziva na kraju kviza
    function endQuiz() {
      // Pripremi formular za slanje podataka
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = "forms.php";
      
      // Dodaj skrivena polja s odgovorima
      const scoreField = document.createElement('input');
      scoreField.type = 'hidden';
      scoreField.name = 'score';
      scoreField.value = score;
      form.appendChild(scoreField);
      
      const totalField = document.createElement('input');
      totalField.type = 'hidden';
      totalField.name = 'total';
      totalField.value = questions.length;
      form.appendChild(totalField);
      
      const temaField = document.createElement('input');
      temaField.type = 'hidden';
      temaField.name = 'tema_id';
      temaField.value = '<?php echo $_SESSION['temaID']; ?>';
      form.appendChild(temaField);
      
      // Dodaj sve odgovore kao JSON string
      const answersField = document.createElement('input');
      answersField.type = 'hidden';
      answersField.name = 'answers';
      answersField.value = JSON.stringify(userAnswers);
      form.appendChild(answersField);
      
      // Dodaj na DOM i pošalji
      document.body.appendChild(form);
      form.submit();
    }

    // Toggle za hint
    function toggleHint() {
      if (hintElement.style.display === "none") {
        hintElement.style.display = "block";
        hintButton.innerHTML = '<i class="fas fa-eye-slash"></i> SAKRIJ';
      } else {
        hintElement.style.display = "none";
        hintButton.innerHTML = '<i class="fas fa-lightbulb"></i> POMOĆ';
      }
    }
    
    // Funkcija za animaciju mehaničkih dijelova
    function createGearsAnimation(count = 15) {
      for (let i = 0; i < count; i++) {
        const gear = document.createElement('div');
        gear.innerHTML = Math.random() > 0.5 ? '<i class="fas fa-cog"></i>' : '<i class="fas fa-microchip"></i>';
        gear.style.position = 'fixed';
        gear.style.color = Math.random() > 0.5 ? '#dc3545' : '#6c757d';
        gear.style.fontSize = Math.random() * 20 + 10 + 'px';
        gear.style.left = Math.random() * 100 + 'vw';
        gear.style.top = '-20px';
        gear.style.opacity = Math.random() * 0.7 + 0.3;
        gear.style.zIndex = '1000';
        gear.style.pointerEvents = 'none';
        document.body.appendChild(gear);
        
        const duration = Math.random() * 3 + 2;
        gear.style.transition = `top ${duration}s linear, transform ${duration}s linear`;
        
        setTimeout(() => {
          gear.style.top = '110vh';
          gear.style.transform = `rotate(${Math.random() * 360}deg)`;
        }, 10);
        
        setTimeout(() => {
          document.body.removeChild(gear);
        }, duration * 1000);
      }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      loadQuestionsFromDB();
      hintButton.addEventListener('click', toggleHint);
      nextButton.addEventListener('click', nextQuestion);
      
      // Dodatno, za zatvaranje modalne slike klikanjem bilo gdje
      window.onclick = function(event) {
        if (event.target == modal) {
          modal.style.display = "none";
        }
      }
    });
  </script>
</body>
</html>
