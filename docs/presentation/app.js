/* 
  SchoolHub Interactive Presentation - Application Logic
  Handles: slide navigation, themes, autoplay, and three custom interactive widgets.
*/

document.addEventListener('DOMContentLoaded', () => {
  // --- Slide Navigation System ---
  const slides = document.querySelectorAll('.slide');
  const btnPrev = document.getElementById('btn-prev');
  const btnNext = document.getElementById('btn-next');
  const progressFill = document.getElementById('progress-fill');
  const slideNumText = document.getElementById('slide-num');
  const btnPlay = document.getElementById('btn-play');
  const autoplayIndicator = document.getElementById('autoplay-indicator');
  const btnTheme = document.getElementById('btn-theme');
  const themeIcon = document.getElementById('theme-icon');
  const btnFullscreen = document.getElementById('btn-fullscreen');

  let currentSlide = 0;
  let autoplayInterval = null;
  const autoplayDuration = 8000; // 8 seconds per slide

  function updateSlides() {
    slides.forEach((slide, idx) => {
      if (idx === currentSlide) {
        slide.classList.add('active');
      } else {
        slide.classList.remove('active');
      }
    });

    // Update buttons
    btnPrev.disabled = currentSlide === 0;
    btnNext.disabled = currentSlide === slides.length - 1;

    // Update progress bar
    const progressPercent = (currentSlide / (slides.length - 1)) * 100;
    progressFill.style.width = `${progressPercent}%`;

    // Update slide index text
    slideNumText.textContent = `${currentSlide + 1} / ${slides.length}`;

    // Perform slide-specific widget initializations/triggers
    onSlideChange(currentSlide);
  }

  function nextSlide() {
    if (currentSlide < slides.length - 1) {
      currentSlide++;
      updateSlides();
    } else if (autoplayInterval) {
      // Loop back to start in autoplay
      currentSlide = 0;
      updateSlides();
    }
  }

  function prevSlide() {
    if (currentSlide > 0) {
      currentSlide--;
      updateSlides();
    }
  }

  // Event Listeners for Nav
  btnPrev.addEventListener('click', () => {
    stopAutoplay();
    prevSlide();
  });

  btnNext.addEventListener('click', () => {
    stopAutoplay();
    nextSlide();
  });

  // Keyboard Navigation
  document.addEventListener('keydown', (e) => {
    // Ignore input events
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'SELECT') {
      return;
    }
    
    if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') {
      stopAutoplay();
      nextSlide();
    } else if (e.key === 'ArrowLeft' || e.key === 'Backspace') {
      stopAutoplay();
      prevSlide();
    }
  });

  // Swipe Navigation for touch screens
  let touchStartX = 0;
  let touchEndX = 0;

  document.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
  }, false);

  document.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
  }, false);

  function handleSwipe() {
    if (touchEndX < touchStartX - 50) {
      stopAutoplay();
      nextSlide(); // Swiped left
    }
    if (touchEndX > touchStartX + 50) {
      stopAutoplay();
      prevSlide(); // Swiped right
    }
  }

  // --- Autoplay Controls ---
  function startAutoplay() {
    autoplayInterval = setInterval(nextSlide, autoplayDuration);
    btnPlay.innerHTML = '⏸'; // Pause character
    autoplayIndicator.classList.add('active');
  }

  function stopAutoplay() {
    if (autoplayInterval) {
      clearInterval(autoplayInterval);
      autoplayInterval = null;
      btnPlay.innerHTML = '▶'; // Play character
      autoplayIndicator.classList.remove('active');
    }
  }

  btnPlay.addEventListener('click', () => {
    if (autoplayInterval) {
      stopAutoplay();
    } else {
      startAutoplay();
    }
  });

  // --- Theme Toggle Controls ---
  // Default is Dark Mode. Light Mode adds 'light-mode' to body.
  const savedTheme = localStorage.getItem('schoolhub-presentation-theme');
  if (savedTheme === 'light') {
    document.body.classList.add('light-mode');
    themeIcon.textContent = '🌙';
  } else {
    document.body.classList.remove('light-mode');
    themeIcon.textContent = '☀️';
  }

  btnTheme.addEventListener('click', () => {
    document.body.classList.toggle('light-mode');
    const isLight = document.body.classList.contains('light-mode');
    localStorage.setItem('schoolhub-presentation-theme', isLight ? 'light' : 'dark');
    themeIcon.textContent = isLight ? '🌙' : '☀️';
  });

  // --- Fullscreen Control ---
  btnFullscreen.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(err => {
        console.error(`Error enabling fullscreen: ${err.message}`);
      });
      btnFullscreen.innerHTML = '⛶';
    } else {
      document.exitFullscreen();
      btnFullscreen.innerHTML = '⛶';
    }
  });


  // --- Slide-Specific Init Actions ---
  function onSlideChange(slideIdx) {
    if (slideIdx === 5) {
      // Billing widget slide
      calculateBilling();
    } else if (slideIdx === 6) {
      // Scheduler widget slide
      resetSchedulerWidget();
    } else if (slideIdx === 7) {
      // Grade calculator slide
      calculateGrades();
    }
  }


  // ==========================================
  // WIDGET 1: 3-TIER ARCHITECTURE CARD EXPANSION (Slide 4)
  // ==========================================
  const tierCards = document.querySelectorAll('.tier-card');
  const detailGroups = document.querySelectorAll('.tier-detail-group');

  tierCards.forEach(card => {
    card.addEventListener('click', () => {
      // Remove active from all cards
      tierCards.forEach(c => c.classList.remove('active'));
      // Add active to clicked card
      card.classList.add('active');

      // Get target details group
      const targetGroup = card.getAttribute('data-tier');
      detailGroups.forEach(group => {
        if (group.id === `detail-tier-${targetGroup}`) {
          group.classList.add('active');
        } else {
          group.classList.remove('active');
        }
      });
    });
  });


  // ==========================================
  // WIDGET 2: SIBLING BILLING WIZARD (Slide 6)
  // ==========================================
  // State for Billing Widget
  const siblingState = {
    sibling1: {
      active: true,
      name: 'Anis (Middle School - CEM)',
      baseTuition: 22000,
      canteen: false,
      transport: false,
      canteenPrice: 5000,
      transportPrice: 7000
    },
    sibling2: {
      active: false,
      name: 'Lina (Primary School)',
      baseTuition: 18000,
      canteen: false,
      transport: false,
      canteenPrice: 5000,
      transportPrice: 7000
    }
  };

  let globalDiscount = 10; // Default sibling discount

  // DOM Elements for Billing
  const chips = document.querySelectorAll('.sibling-chip');
  const tuitionPriceText = document.getElementById('tuition-price');
  const canteenBtn = document.getElementById('fee-canteen');
  const transportBtn = document.getElementById('fee-transport');
  const discountSlider = document.getElementById('billing-discount-slider');
  const discountValText = document.getElementById('discount-val-text');
  const grandTotalText = document.getElementById('grand-total-val');
  const discountAmountText = document.getElementById('discount-amount-val');
  const netTotalText = document.getElementById('net-total-val');
  const monthlyBillText = document.getElementById('monthly-bill-val');
  const billsContainer = document.getElementById('bills-scroll');

  let activeSiblingKey = 'sibling1';

  // Toggle Siblings
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const target = chip.getAttribute('data-sibling');
      
      // If clicking inactive sibling, we toggle its active state
      if (target !== activeSiblingKey) {
        // Toggle selected sibling active state
        siblingState[target].active = !siblingState[target].active;
        
        // Refresh chip UI visual state
        if (siblingState[target].active) {
          chip.classList.add('active');
        } else {
          chip.classList.remove('active');
        }
        
        // We set current editor sibling to this clicked sibling if it's active
        if (siblingState[target].active) {
          activeSiblingKey = target;
          // De-select active styling from other siblings if we are single-focused (for options)
          chips.forEach(c => {
            if (c.getAttribute('data-sibling') === activeSiblingKey) {
              c.classList.add('focused-chip'); // highlighted for editing
            } else {
              c.classList.remove('focused-chip');
            }
          });
        }
      } else {
        // Clicking already active, we just toggle focus. 
        // Note: at least one sibling must be active to calculate
        const activeCount = Object.values(siblingState).filter(s => s.active).length;
        if (activeCount > 1) {
          siblingState[target].active = false;
          chip.classList.remove('active');
          // Switch editor to another active sibling
          activeSiblingKey = Object.keys(siblingState).find(k => siblingState[k].active);
        }
      }
      
      syncSiblingOptionsUI();
      calculateBilling();
    });
  });

  // Sync Checkbox Buttons for Active Sibling options
  function syncSiblingOptionsUI() {
    const sib = siblingState[activeSiblingKey];
    
    // Highlight editing sibling
    chips.forEach(c => {
      if (c.getAttribute('data-sibling') === activeSiblingKey) {
        c.style.borderColor = 'var(--color-primary)';
      } else {
        c.style.borderColor = 'var(--border-color)';
      }
    });

    // Update Tuition Display
    tuitionPriceText.textContent = `${sib.baseTuition.toLocaleString()} DZD`;
    
    // Update Canteen UI
    if (sib.canteen) {
      canteenBtn.classList.add('active');
    } else {
      canteenBtn.classList.remove('active');
    }
    
    // Update Transport UI
    if (sib.transport) {
      transportBtn.classList.add('active');
    } else {
      transportBtn.classList.remove('active');
    }
  }

  // Option Click Handler
  canteenBtn.addEventListener('click', () => {
    siblingState[activeSiblingKey].canteen = !siblingState[activeSiblingKey].canteen;
    syncSiblingOptionsUI();
    calculateBilling();
  });

  transportBtn.addEventListener('click', () => {
    siblingState[activeSiblingKey].transport = !siblingState[activeSiblingKey].transport;
    syncSiblingOptionsUI();
    calculateBilling();
  });

  // Slider change
  discountSlider.addEventListener('input', (e) => {
    globalDiscount = parseInt(e.target.value);
    discountValText.textContent = `${globalDiscount}%`;
    calculateBilling();
  });

  function calculateBilling() {
    let rawTotal = 0;
    
    // Sum for all active siblings
    Object.keys(siblingState).forEach(key => {
      const s = siblingState[key];
      if (s.active) {
        rawTotal += s.baseTuition;
        if (s.canteen) rawTotal += s.canteenPrice;
        if (s.transport) rawTotal += s.transportPrice;
      }
    });

    const discountAmount = Math.round(rawTotal * (globalDiscount / 100));
    const netTotal = rawTotal - discountAmount;
    const monthlyBill = Math.round(netTotal / 10); // 10 month contract standard

    // Write to DOM
    grandTotalText.textContent = `${rawTotal.toLocaleString()} DZD`;
    discountAmountText.textContent = `-${discountAmount.toLocaleString()} DZD`;
    netTotalText.textContent = `${netTotal.toLocaleString()} DZD`;
    monthlyBillText.textContent = `${monthlyBill.toLocaleString()} DZD`;

    // Render monthly bills list
    billsContainer.innerHTML = '';
    const months = ['September', 'October', 'November', 'December', 'January', 'February', 'March', 'April', 'May', 'June'];
    
    months.forEach((month, index) => {
      const row = document.createElement('div');
      row.className = 'bill-item-row';
      row.style.animationDelay = `${index * 0.04}s`;
      
      row.innerHTML = `
        <span class="bill-month">${month} 2026</span>
        <div style="display:flex; align-items:center; gap:12px;">
          <span class="bill-amount">${monthlyBill.toLocaleString()} DZD</span>
          <span class="bill-badge">Unpaid</span>
        </div>
      `;
      billsContainer.appendChild(row);
    });
  }

  // Initial UI sync
  syncSiblingOptionsUI();


  // ==========================================
  // WIDGET 3: SCHEDULER WALKTHROUGH (Slide 7)
  // ==========================================
  let schedulerActiveStep = 0;
  const schedulerStepsMax = 5;

  const stepCards = document.querySelectorAll('.step-card');
  const schedulerPrevBtn = document.getElementById('scheduler-prev');
  const schedulerNextBtn = document.getElementById('scheduler-next');
  const stepProgressText = document.getElementById('step-progress-text');
  const timetableCells = document.querySelectorAll('.timetable-grid .grid-cell');

  function resetSchedulerWidget() {
    schedulerActiveStep = 0;
    updateSchedulerUI();
  }

  function updateSchedulerUI() {
    // Sync steps status
    stepCards.forEach((card, index) => {
      card.classList.remove('active', 'completed');
      if (index === schedulerActiveStep) {
        card.classList.add('active');
      } else if (index < schedulerActiveStep) {
        card.classList.add('completed');
      }
    });

    // Button states
    schedulerPrevBtn.disabled = schedulerActiveStep === 0;
    
    if (schedulerActiveStep === schedulerStepsMax - 1) {
      schedulerNextBtn.innerHTML = '🔄 Reset';
    } else {
      schedulerNextBtn.innerHTML = 'Next Step →';
    }

    stepProgressText.textContent = `Step ${schedulerActiveStep + 1} of ${schedulerStepsMax}`;

    // Update timetable grid visuals
    timetableCells.forEach(cell => {
      // Clear cells that aren't headers or time columns
      if (!cell.classList.contains('grid-header') && !cell.classList.contains('time-col')) {
        cell.className = 'grid-cell';
        cell.innerHTML = '-';
      }
    });

    // Step-by-step mock allocations
    if (schedulerActiveStep >= 1) {
      // Step 2 onwards: Allocate Sport Blocks
      // Let's place sport on Thursday 3rd & 4th hour
      const sportCell1 = document.getElementById('cell-thu-3');
      const sportCell2 = document.getElementById('cell-thu-4');
      if (sportCell1 && sportCell2) {
        sportCell1.className = 'grid-cell filled-sport';
        sportCell1.innerHTML = 'Sport<br><span style="font-size:0.6rem;opacity:0.8;">M. Khaled (Field)</span>';
        sportCell2.className = 'grid-cell filled-sport';
        sportCell2.innerHTML = 'Sport<br><span style="font-size:0.6rem;opacity:0.8;">M. Khaled (Field)</span>';
      }
    }

    if (schedulerActiveStep >= 2) {
      // Step 3 onwards: Allocate primary classes (Math, Arabic)
      const allocations = [
        { id: 'cell-mon-1', subject: 'Math', teacher: 'Mme. Sarah' },
        { id: 'cell-mon-2', subject: 'Math', teacher: 'Mme. Sarah' },
        { id: 'cell-tue-1', subject: 'Arabic', teacher: 'M. Ali' },
        { id: 'cell-tue-2', subject: 'Arabic', teacher: 'M. Ali' },
        { id: 'cell-wed-1', subject: 'Math', teacher: 'Mme. Sarah' },
        { id: 'cell-wed-2', subject: 'Arabic', teacher: 'M. Ali' }
      ];

      allocations.forEach(alloc => {
        const cell = document.getElementById(alloc.id);
        if (cell) {
          cell.className = 'grid-cell filled-class';
          cell.innerHTML = `${alloc.subject}<br><span style="font-size:0.6rem;opacity:0.8;">${alloc.teacher}</span>`;
        }
      });
    }

    if (schedulerActiveStep >= 3) {
      // Step 4 onwards: Fallback scheduler active
      // Allocate French and Science under soft constraints
      const allocations = [
        { id: 'cell-mon-3', subject: 'French', teacher: 'Mme. Amina', warning: true },
        { id: 'cell-tue-3', subject: 'Physics', teacher: 'M. Omar' },
        { id: 'cell-wed-3', subject: 'French', teacher: 'Mme. Amina' },
        { id: 'cell-thu-1', subject: 'Islamic', teacher: 'M. Abderrahmane' },
        { id: 'cell-thu-2', subject: 'History', teacher: 'M. Kamel' }
      ];

      allocations.forEach(alloc => {
        const cell = document.getElementById(alloc.id);
        if (cell) {
          cell.className = 'grid-cell filled-class';
          if (alloc.warning) {
            cell.style.borderStyle = 'dashed';
            cell.style.borderColor = 'var(--color-secondary)';
          }
          cell.innerHTML = `${alloc.subject}<br><span style="font-size:0.6rem;opacity:0.8;">${alloc.teacher}</span>`;
        }
      });
    }

    if (schedulerActiveStep >= 4) {
      // Step 5: Completed timetable! Add a glow effect to timetable container
      const grid = document.querySelector('.timetable-grid');
      if (grid) {
        grid.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.2)';
        grid.style.borderColor = 'var(--color-success)';
      }
      
      // Fill remaining slots
      const finalAllocations = [
        { id: 'cell-mon-4', subject: 'English', teacher: 'Mme. Fatiha' },
        { id: 'cell-tue-4', subject: 'Civics', teacher: 'M. Kamel' },
        { id: 'cell-wed-4', subject: 'Science', teacher: 'Mme. Yasmin' }
      ];

      finalAllocations.forEach(alloc => {
        const cell = document.getElementById(alloc.id);
        if (cell) {
          cell.className = 'grid-cell filled-class';
          cell.innerHTML = `${alloc.subject}<br><span style="font-size:0.6rem;opacity:0.8;">${alloc.teacher}</span>`;
        }
      });
    } else {
      const grid = document.querySelector('.timetable-grid');
      if (grid) {
        grid.style.boxShadow = 'none';
        grid.style.borderColor = 'var(--border-color)';
      }
    }
  }

  schedulerNextBtn.addEventListener('click', () => {
    if (schedulerActiveStep === schedulerStepsMax - 1) {
      schedulerActiveStep = 0;
    } else {
      schedulerActiveStep++;
    }
    updateSchedulerUI();
  });

  schedulerPrevBtn.addEventListener('click', () => {
    if (schedulerActiveStep > 0) {
      schedulerActiveStep--;
      updateSchedulerUI();
    }
  });


  // ==========================================
  // WIDGET 4: GRADE CALCULATOR WIDGET (Slide 8)
  // ==========================================
  // Mock Subjects Coefficients
  const subjectCoefficients = {
    math: 5,
    arabic: 4,
    physics: 3
  };

  let activeSubject = 'math';

  // Grades state for subjects
  const gradesState = {
    math: { cc: 15, ccMax: 20, devoir: 12, devoirMax: 20, composition: 14, compMax: 20 },
    arabic: { cc: 17, ccMax: 20, devoir: 15, devoirMax: 20, composition: 13, compMax: 20 },
    physics: { cc: 11, ccMax: 20, devoir: 14, devoirMax: 20, composition: 10, compMax: 20 }
  };

  // DOM Elements for Grades
  const subChips = document.querySelectorAll('.coef-chip');
  const ccVal = document.getElementById('grade-cc-val');
  const ccMax = document.getElementById('grade-cc-max');
  const ccNorm = document.getElementById('cc-norm');
  const devVal = document.getElementById('grade-dev-val');
  const devMax = document.getElementById('grade-dev-max');
  const devNorm = document.getElementById('dev-norm');
  const compVal = document.getElementById('grade-comp-val');
  const compMax = document.getElementById('grade-comp-max');
  const compNorm = document.getElementById('comp-norm');
  const subjectAvgText = document.getElementById('subject-avg-val');
  const overallAvgText = document.getElementById('overall-avg-val');
  const gradeBarFill = document.getElementById('grade-bar-fill');

  subChips.forEach(chip => {
    chip.addEventListener('click', () => {
      subChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      activeSubject = chip.getAttribute('data-subject');
      syncGradesForm();
    });
  });

  function syncGradesForm() {
    const grades = gradesState[activeSubject];
    
    // Set Input values
    ccVal.value = grades.cc;
    ccMax.value = grades.ccMax;
    devVal.value = grades.devoir;
    devMax.value = grades.devoirMax;
    compVal.value = grades.composition;
    compMax.value = grades.compMax;

    calculateGrades();
  }

  // Hook input changes
  const numInputs = document.querySelectorAll('.grading-widget input');
  numInputs.forEach(input => {
    input.addEventListener('input', () => {
      // Validate inputs
      let val = parseFloat(input.value) || 0;
      const isMax = input.id.includes('max');
      
      if (val < 0) val = 0;
      
      // Save changes back to state
      const grades = gradesState[activeSubject];
      if (input.id === 'grade-cc-val') grades.cc = Math.min(val, grades.ccMax);
      if (input.id === 'grade-cc-max') grades.ccMax = Math.max(1, val);
      if (input.id === 'grade-dev-val') grades.devoir = Math.min(val, grades.devoirMax);
      if (input.id === 'grade-dev-max') grades.devoirMax = Math.max(1, val);
      if (input.id === 'grade-comp-val') grades.composition = Math.min(val, grades.compMax);
      if (input.id === 'grade-comp-max') grades.compMax = Math.max(1, val);

      // Clamp values
      if (grades.cc > grades.ccMax) grades.cc = grades.ccMax;
      if (grades.devoir > grades.devoirMax) grades.devoir = grades.devoirMax;
      if (grades.composition > grades.compMax) grades.composition = grades.compMax;

      calculateGrades();
    });
  });

  function calculateGrades() {
    // 1. Calculate and normalize for each subject in state
    const averages = {};
    
    Object.keys(gradesState).forEach(key => {
      const g = gradesState[key];
      const ccN = (g.cc / g.ccMax) * 20;
      const devN = (g.devoir / g.devoirMax) * 20;
      const compN = (g.composition / g.compMax) * 20;
      
      // Formula: (CC + Devoir + Composition * 2) / 4
      const subjectAvg = ((ccN * 1) + (devN * 1) + (compN * 2)) / 4;
      averages[key] = {
        ccN,
        devN,
        compN,
        avg: Math.round(subjectAvg * 100) / 100
      };
    });

    // 2. Render Normalised & Subject Averages for current active subject
    const activeResults = averages[activeSubject];
    ccNorm.textContent = `${activeResults.ccN.toFixed(2)}/20`;
    devNorm.textContent = `${activeResults.devN.toFixed(2)}/20`;
    compNorm.textContent = `${activeResults.compN.toFixed(2)}/20`;
    
    subjectAvgText.textContent = `${activeResults.avg.toFixed(2)} / 20`;

    // 3. Compute Weighted Overall Average
    let weightedSum = 0;
    let coeffSum = 0;

    Object.keys(averages).forEach(key => {
      const coef = subjectCoefficients[key];
      weightedSum += averages[key].avg * coef;
      coeffSum += coef;
    });

    const overallAvg = weightedSum / coeffSum;
    overallAvgText.textContent = `${overallAvg.toFixed(2)} / 20`;

    // Update grade progress bar
    const barPercent = (overallAvg / 20) * 100;
    gradeBarFill.style.width = `${barPercent}%`;

    // Change bar color based on overall average
    if (overallAvg >= 14) {
      gradeBarFill.style.background = 'linear-gradient(90deg, var(--color-success), var(--color-primary))';
    } else if (overallAvg >= 10) {
      gradeBarFill.style.background = 'linear-gradient(90deg, var(--color-secondary), var(--color-primary))';
    } else {
      gradeBarFill.style.background = 'linear-gradient(90deg, var(--color-danger), var(--color-secondary))';
    }
  }

  // Initialize grade values on startup
  syncGradesForm();

  // Initialize slide states
  updateSlides();
});
