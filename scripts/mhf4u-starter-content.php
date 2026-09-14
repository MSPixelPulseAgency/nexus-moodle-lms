<?php

defined('CLI_SCRIPT') || die();

return [
    'description' => 'This original starter course supports Ontario Grade 12 Advanced Functions learners through concise lessons, worked examples, guided practice, checkpoint quizzes, and graded applications. It is designed as a teacher-ready foundation that can be extended with school-specific pacing and assessment policies.',
    'welcome' => [
        'section' => 0,
        'title' => 'Course Welcome and Overview',
        'summary' => 'Start here for course goals, navigation, assessment expectations, learning routines, and academic integrity.',
        'page' => <<<'HTML'
<h2>Welcome to Advanced Functions</h2>
<p>MHF4U develops the algebraic, graphical, numerical, and reasoning skills needed to analyze advanced functions. The course prepares learners for calculus, data management, and other postsecondary mathematics.</p>
<h3>Course goals</h3>
<ul>
  <li>Represent and analyze polynomial, rational, exponential, logarithmic, and trigonometric functions.</li>
  <li>Connect equations, tables, graphs, and real-world interpretations.</li>
  <li>Communicate complete solutions using correct notation, stated restrictions, and justified conclusions.</li>
  <li>Use technology to test ideas while preserving clear mathematical reasoning.</li>
</ul>
<h3>How to work through each unit</h3>
<ol>
  <li>Read the lesson and reproduce each worked example in your own notes.</li>
  <li>Complete the guided practice without notes, then use the answer checks to correct your reasoning.</li>
  <li>Take the checkpoint quiz. Revisit any outcome that is not yet secure.</li>
  <li>Submit the graded application as directed by your teacher.</li>
</ol>
<h3>Assessment and feedback</h3>
<p>Checkpoint quizzes provide quick formative feedback. Unit applications are graded activities that assess knowledge, thinking, communication, and application. Your teacher may adjust weights, dates, and submission requirements before the course begins.</p>
<h3>Academic integrity</h3>
<p>Submit your own work, identify any permitted sources or tools, and show enough reasoning for another person to follow your solution. Ask your teacher before collaborating. Copying solutions, sharing assessment answers, or presenting generated work as your own is not acceptable.</p>
<h3>Success routine</h3>
<p>Use a consistent weekly schedule, keep an error log, check restrictions before solving, label graphs, and verify answers in the original equation. Contact your teacher early when a concept remains unclear.</p>
HTML,
    ],
    'units' => [
        [
            'section' => 1,
            'title' => 'Functions and Transformations',
            'summary' => 'Function notation, domain and range, inverses, and transformations of parent functions.',
            'lesson' => <<<'HTML'
<h2>Functions and Transformations</h2>
<p>A function assigns exactly one output to each permitted input. Use <strong>f(x)</strong> to name the output produced by input <strong>x</strong>. The domain is the set of allowed inputs; the range is the set of possible outputs.</p>
<h3>Domain and range</h3>
<ul>
  <li>For a polynomial, the domain is all real numbers.</li>
  <li>For a rational expression, exclude values that make a denominator zero.</li>
  <li>For an even root, require the radicand to be non-negative.</li>
</ul>
<h3>Inverse functions</h3>
<p>An inverse reverses the input-output process. To find an inverse algebraically, write y = f(x), interchange x and y, solve for y, and state any needed restriction. A function must be one-to-one on its domain to have an inverse that is also a function.</p>
<h3>Transformation model</h3>
<p>For <strong>y = a f(k(x - d)) + c</strong>, d shifts horizontally, c shifts vertically, a controls vertical stretch or reflection, and k controls horizontal scale or reflection.</p>
<h3>Worked example</h3>
<p>Start with y = x<sup>2</sup>. For g(x) = -2(x - 3)<sup>2</sup> + 5, move right 3 and up 5, reflect in the x-axis, and vertically stretch by factor 2. The vertex is (3, 5), the axis is x = 3, the domain is all real numbers, and the range is y ≤ 5.</p>
<p>If f(x) = 3x - 7, then x = 3y - 7 after interchanging variables, so f<sup>-1</sup>(x) = (x + 7)/3.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>Find the domain of f(x) = √(2x - 6).</li>
  <li>Find the inverse of f(x) = (x + 4)/5.</li>
  <li>Describe every transformation from y = |x| to y = -3|x + 2| + 1.</li>
  <li>State the vertex and range of y = 0.5(x - 6)<sup>2</sup> - 4.</li>
  <li>Determine whether f(x) = x<sup>2</sup> has an inverse on all real numbers. Suggest a domain restriction if needed.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>x ≥ 3.</li><li>f<sup>-1</sup>(x) = 5x - 4.</li><li>Left 2, vertical stretch by 3, reflection in the x-axis, up 1.</li><li>Vertex (6, -4); range y ≥ -4.</li><li>No; restrict to x ≥ 0 or x ≤ 0.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Domain of a radical', 'text' => 'What is the domain of f(x) = √(x - 3)?', 'answers' => ['x ≥ 3', 'x ≤ 3', 'x > -3', 'All real numbers'], 'correct' => 0, 'feedback' => 'The radicand x - 3 must be non-negative.'],
                ['name' => 'Inverse of a linear function', 'text' => 'If f(x) = 3x - 5, which expression equals f⁻¹(x)?', 'answers' => ['(x + 5)/3', '3x + 5', '(x - 5)/3', '5 - 3x'], 'correct' => 0, 'feedback' => 'Interchange x and y, then solve x = 3y - 5 for y.'],
                ['name' => 'Quadratic transformation', 'text' => 'Which description matches y = -2(x - 4)² + 1 relative to y = x²?', 'answers' => ['Right 4, reflect in the x-axis, vertical stretch by 2, up 1', 'Left 4, vertical compression by 2, down 1', 'Right 1, reflect in the y-axis, up 4', 'Left 4, reflect in the x-axis, up 1'], 'correct' => 0, 'feedback' => 'Read the transformation parameters from a = -2, d = 4, and c = 1.'],
            ],
            'assignment' => <<<'HTML'
<h2>Transformation Portfolio</h2>
<p>Create a concise portfolio that compares three transformed functions: one quadratic, one absolute-value, and one reciprocal function.</p>
<ol>
  <li>Choose a transformed equation for each parent function and identify a, k, d, and c.</li>
  <li>Describe the transformations in the order you would apply them.</li>
  <li>Provide a labelled graph with at least five accurate points or key features.</li>
  <li>State domain, range, intercepts, and asymptotes where applicable.</li>
  <li>Verify one point algebraically for each function.</li>
</ol>
<p>Submit one readable PDF or an online-text response. Include complete reasoning and use original examples.</p>
HTML,
        ],
        [
            'section' => 2,
            'title' => 'Polynomial Functions',
            'summary' => 'Polynomial characteristics, factoring, remainder and factor theorems, zeros, intercepts, and end behaviour.',
            'lesson' => <<<'HTML'
<h2>Polynomial Functions</h2>
<p>A polynomial function has non-negative integer exponents. Its degree and leading coefficient determine end behaviour, while its factors reveal zeros and possible multiplicities.</p>
<h3>Key relationships</h3>
<ul>
  <li>If P(a) = 0, then x - a is a factor and a is a zero.</li>
  <li>The remainder after division by x - a is P(a).</li>
  <li>A zero of odd multiplicity crosses the x-axis; a zero of even multiplicity touches and turns.</li>
  <li>A degree n polynomial has at most n real zeros and at most n - 1 turning points.</li>
</ul>
<h3>Worked example</h3>
<p>For P(x) = x<sup>3</sup> - 4x<sup>2</sup> - x + 4, factor by grouping:</p>
<p>P(x) = x<sup>2</sup>(x - 4) - 1(x - 4) = (x<sup>2</sup> - 1)(x - 4) = (x - 1)(x + 1)(x - 4).</p>
<p>The zeros are -1, 1, and 4. Each has multiplicity 1, so the graph crosses at each x-intercept. The positive leading cubic falls left and rises right.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>Use the remainder theorem to find the remainder when P(x) = 2x<sup>3</sup> - x + 5 is divided by x - 2.</li>
  <li>Factor x<sup>3</sup> + 2x<sup>2</sup> - 9x - 18.</li>
  <li>State the zeros and multiplicities of (x + 3)<sup>2</sup>(x - 1)<sup>3</sup>.</li>
  <li>Describe the end behaviour of -2x<sup>4</sup> + 7x.</li>
  <li>What is the maximum possible number of turning points for a degree 6 polynomial?</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>P(2) = 19.</li><li>(x + 2)(x - 3)(x + 3).</li><li>-3 has multiplicity 2; 1 has multiplicity 3.</li><li>Both ends fall.</li><li>5.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Remainder theorem', 'text' => 'What is the remainder when P(x) = x³ - 2x + 1 is divided by x - 2?', 'answers' => ['5', '3', '-5', '0'], 'correct' => 0, 'feedback' => 'The remainder is P(2) = 8 - 4 + 1 = 5.'],
                ['name' => 'Multiplicity behaviour', 'text' => 'How does a polynomial graph behave at a zero with even multiplicity?', 'answers' => ['It touches the x-axis and turns', 'It always crosses the x-axis', 'It has a vertical asymptote', 'It has no y-intercept'], 'correct' => 0, 'feedback' => 'An even-multiplicity factor does not change sign at the zero.'],
                ['name' => 'End behaviour', 'text' => 'Which end behaviour matches a polynomial with odd degree and negative leading coefficient?', 'answers' => ['Rises left and falls right', 'Falls left and rises right', 'Both ends rise', 'Both ends fall'], 'correct' => 0, 'feedback' => 'A negative odd-degree polynomial has positive values far left and negative values far right.'],
            ],
            'assignment' => <<<'HTML'
<h2>Polynomial Model and Analysis</h2>
<p>Analyze P(x) = -(x + 2)(x - 1)<sup>2</sup>(x - 4).</p>
<ol>
  <li>State the degree, leading coefficient, zeros, and multiplicities.</li>
  <li>Determine the y-intercept and describe end behaviour.</li>
  <li>Sketch a labelled graph consistent with all key features.</li>
  <li>Explain how each multiplicity affects the graph.</li>
  <li>Create a realistic context in which a restricted portion of this polynomial could model a quantity, and state a reasonable domain.</li>
</ol>
HTML,
        ],
        [
            'section' => 3,
            'title' => 'Rational Functions',
            'summary' => 'Restrictions, intercepts, asymptotes, graphing, and solving rational equations.',
            'lesson' => <<<'HTML'
<h2>Rational Functions</h2>
<p>A rational function is a quotient of polynomials. Restrictions come from the original denominator, even if a common factor later cancels.</p>
<h3>Graph features</h3>
<ul>
  <li>A non-cancelled denominator zero usually produces a vertical asymptote.</li>
  <li>A cancelled common factor produces a removable discontinuity, or hole.</li>
  <li>Compare numerator and denominator degrees to determine horizontal or oblique asymptotes.</li>
  <li>Find x-intercepts from non-cancelled numerator zeros and the y-intercept by evaluating at x = 0 when permitted.</li>
</ul>
<h3>Worked example</h3>
<p>For f(x) = (x + 1)/(x - 2), the restriction is x ≠ 2 and the vertical asymptote is x = 2. Equal degrees give horizontal asymptote y = 1. The x-intercept is (-1, 0), and f(0) = -1/2.</p>
<p>To solve 2/(x - 1) = 3/(x + 2), first state x ≠ 1, -2. Cross-multiplying gives 2(x + 2) = 3(x - 1), so x = 7, which is permitted.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>State the restrictions for (x + 4)/(x<sup>2</sup> - 9).</li>
  <li>Identify the vertical and horizontal asymptotes of (2x - 1)/(x + 5).</li>
  <li>Find the hole in (x<sup>2</sup> - 4)/(x - 2).</li>
  <li>Solve 1/x + 1/(x + 2) = 1, checking restrictions.</li>
  <li>Explain why multiplying both sides by a variable expression requires a final solution check.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>x ≠ -3, 3.</li><li>x = -5; y = 2.</li><li>The simplified function is x + 2 with a hole at (2, 4).</li><li>x = ±√2, and both satisfy the restrictions.</li><li>Multiplication can introduce a restricted or extraneous value.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Rational restriction', 'text' => 'Which values are excluded from the domain of (x + 1)/(x² - 4)?', 'answers' => ['x = -2 and x = 2', 'x = -1 only', 'x = 2 only', 'No values'], 'correct' => 0, 'feedback' => 'Factor the denominator as (x - 2)(x + 2).'],
                ['name' => 'Horizontal asymptote', 'text' => 'What is the horizontal asymptote of (3x - 2)/(x + 4)?', 'answers' => ['y = 3', 'x = -4', 'y = -2', 'y = 0'], 'correct' => 0, 'feedback' => 'For equal degrees, divide the leading coefficients.'],
                ['name' => 'Hole versus asymptote', 'text' => 'What feature is created when a factor cancels from both numerator and denominator?', 'answers' => ['A removable discontinuity', 'A horizontal asymptote', 'A turning point', 'An absolute maximum'], 'correct' => 0, 'feedback' => 'The original restriction remains as a hole after simplification.'],
            ],
            'assignment' => <<<'HTML'
<h2>Rational Function Investigation</h2>
<p>Investigate R(x) = (x<sup>2</sup> - x - 6)/(x<sup>2</sup> - 4).</p>
<ol>
  <li>Factor fully and state all restrictions from the original expression.</li>
  <li>Identify holes, vertical asymptotes, and the horizontal asymptote.</li>
  <li>Find all intercepts that exist.</li>
  <li>Sketch a labelled graph and verify one point in each interval separated by a vertical asymptote.</li>
  <li>Explain the difference between the hole and the vertical asymptote in this example.</li>
</ol>
HTML,
        ],
        [
            'section' => 4,
            'title' => 'Exponential and Logarithmic Functions',
            'summary' => 'Exponent and logarithm laws, graphs, equations, and applications.',
            'lesson' => <<<'HTML'
<h2>Exponential and Logarithmic Functions</h2>
<p>For b &gt; 0 and b ≠ 1, y = b<sup>x</sup> and y = log<sub>b</sub>(x) are inverse functions. The exponential domain is all real numbers with positive range; the logarithmic domain is positive with real-number range.</p>
<h3>Useful laws</h3>
<ul>
  <li>b<sup>m</sup>b<sup>n</sup> = b<sup>m+n</sup> and (b<sup>m</sup>)<sup>n</sup> = b<sup>mn</sup>.</li>
  <li>log<sub>b</sub>(MN) = log<sub>b</sub>M + log<sub>b</sub>N.</li>
  <li>log<sub>b</sub>(M/N) = log<sub>b</sub>M - log<sub>b</sub>N.</li>
  <li>log<sub>b</sub>(M<sup>p</sup>) = p log<sub>b</sub>M.</li>
</ul>
<h3>Worked examples</h3>
<p>Solve 3<sup>2x-1</sup> = 27. Since 27 = 3<sup>3</sup>, set 2x - 1 = 3, giving x = 2.</p>
<p>Solve log<sub>2</sub>(x - 1) + log<sub>2</sub>(x + 1) = 3. The domain requires x &gt; 1. Combine to log<sub>2</sub>(x<sup>2</sup> - 1) = 3, so x<sup>2</sup> - 1 = 8 and x = ±3. Only x = 3 satisfies the domain.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>Solve 5<sup>x+1</sup> = 125.</li>
  <li>Expand log<sub>3</sub>(9x<sup>2</sup>/y).</li>
  <li>Condense 2 ln x - ln(x - 1).</li>
  <li>Solve log<sub>10</sub>(x + 4) = 2 and check the domain.</li>
  <li>A quantity starts at 800 and grows 6% per year. Write a model and find its value after 5 years.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>x = 2.</li><li>2 + 2log<sub>3</sub>x - log<sub>3</sub>y.</li><li>ln(x<sup>2</sup>/(x - 1)).</li><li>x = 96.</li><li>A(t) = 800(1.06)<sup>t</sup>; A(5) ≈ 1070.58.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Exponential equation', 'text' => 'Solve 2^(x + 1) = 16.', 'answers' => ['x = 3', 'x = 4', 'x = 7', 'x = 15'], 'correct' => 0, 'feedback' => 'Write 16 as 2⁴, then equate exponents.'],
                ['name' => 'Logarithm domain', 'text' => 'What is the domain of f(x) = log(x - 5)?', 'answers' => ['x > 5', 'x ≥ 5', 'x < 5', 'All real numbers'], 'correct' => 0, 'feedback' => 'A logarithm requires a positive argument.'],
                ['name' => 'Logarithm law', 'text' => 'Which expression equals log_b(MN)?', 'answers' => ['log_b(M) + log_b(N)', 'log_b(M)log_b(N)', 'log_b(M) - log_b(N)', 'N log_b(M)'], 'correct' => 0, 'feedback' => 'The logarithm of a product becomes a sum.'],
            ],
            'assignment' => <<<'HTML'
<h2>Growth and Decay Comparison</h2>
<p>Compare one growth model and one decay model drawn from plausible Canadian contexts such as savings, population, medication concentration, or equipment value.</p>
<ol>
  <li>Define variables, initial value, rate, and time unit for each model.</li>
  <li>Write each exponential equation and state its domain in context.</li>
  <li>Calculate values at three meaningful times.</li>
  <li>Use logarithms to solve one time-to-target question for each model.</li>
  <li>Interpret both answers with units and discuss one limitation of each model.</li>
</ol>
HTML,
        ],
        [
            'section' => 5,
            'title' => 'Trigonometric Functions',
            'summary' => 'Radians, identities, sinusoidal models, transformations, and equations.',
            'lesson' => <<<'HTML'
<h2>Trigonometric Functions</h2>
<p>Radians measure angle by arc length divided by radius. One complete revolution is 2π radians. The sine and cosine functions model periodic change.</p>
<h3>Core ideas</h3>
<ul>
  <li>Convert degrees to radians by multiplying by π/180.</li>
  <li>For y = a sin(k(x - d)) + c, amplitude is |a|, period is 2π/|k|, phase shift is d, and midline is y = c.</li>
  <li>The identity sin<sup>2</sup>x + cos<sup>2</sup>x = 1 connects sine and cosine.</li>
  <li>When solving, use the unit circle and include every solution in the requested interval.</li>
</ul>
<h3>Worked example</h3>
<p>For y = 3cos(2(x - π/4)) - 1, the amplitude is 3, period is π, phase shift is π/4 right, and midline is y = -1.</p>
<p>On 0 ≤ x &lt; 2π, solve 2sin x = 1. Since sin x = 1/2, the solutions are x = π/6 and x = 5π/6.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>Convert 225° to radians.</li>
  <li>State the amplitude, period, phase shift, and midline of y = -2sin(3(x + π/6)) + 4.</li>
  <li>Simplify (1 - cos<sup>2</sup>x)/sin x where defined.</li>
  <li>Solve cos x = -√2/2 on 0 ≤ x &lt; 2π.</li>
  <li>Write a sinusoidal model with maximum 9, minimum 1, and period 6.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>5π/4.</li><li>Amplitude 2; period 2π/3; left π/6; midline y = 4.</li><li>sin x.</li><li>3π/4 and 5π/4.</li><li>One answer is y = 4cos((π/3)x) + 5.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Degree to radian conversion', 'text' => 'What is 150° in radians?', 'answers' => ['5π/6', '3π/4', '2π/3', '7π/6'], 'correct' => 0, 'feedback' => 'Multiply 150 by π/180 and simplify.'],
                ['name' => 'Sinusoidal period', 'text' => 'What is the period of y = sin(4x)?', 'answers' => ['π/2', '2π', '4π', 'π/4'], 'correct' => 0, 'feedback' => 'Period = 2π/|k| = 2π/4.'],
                ['name' => 'Pythagorean identity', 'text' => 'Which expression equals 1 - cos²x?', 'answers' => ['sin²x', 'cos²x', 'tan²x', '2sin x'], 'correct' => 0, 'feedback' => 'Rearrange sin²x + cos²x = 1.'],
            ],
            'assignment' => <<<'HTML'
<h2>Sinusoidal Modelling Task</h2>
<p>Build a sinusoidal model for a periodic situation such as daylight hours, a rotating wheel, or a repeating temperature cycle.</p>
<ol>
  <li>State a plausible maximum, minimum, period, and starting position.</li>
  <li>Determine amplitude, midline, angular frequency, and phase shift.</li>
  <li>Write a sine or cosine model and define every variable with units.</li>
  <li>Graph at least one full cycle with key points labelled.</li>
  <li>Use the model to answer two questions and interpret the results.</li>
</ol>
HTML,
        ],
        [
            'section' => 6,
            'title' => 'Combining Functions and Rates of Change',
            'summary' => 'Operations, compositions, average and instantaneous rates of change, and applications.',
            'lesson' => <<<'HTML'
<h2>Combining Functions and Rates of Change</h2>
<p>Functions can be added, subtracted, multiplied, divided, and composed. The domain of a combination must satisfy every operation involved.</p>
<h3>Composition</h3>
<p>(f ◦ g)(x) = f(g(x)). Evaluate the inside function first. For a quotient f/g, also exclude inputs where g(x) = 0.</p>
<h3>Rates of change</h3>
<p>The average rate of change from x = a to x = b is [f(b) - f(a)]/(b - a). It is the slope of a secant line. An instantaneous rate can be estimated by average rates over increasingly small intervals around the input.</p>
<h3>Worked example</h3>
<p>Let f(x) = x<sup>2</sup> + 1 and g(x) = 2x - 3. Then (f ◦ g)(x) = (2x - 3)<sup>2</sup> + 1, while (g ◦ f)(x) = 2(x<sup>2</sup> + 1) - 3 = 2x<sup>2</sup> - 1.</p>
<p>For h(t) = t<sup>2</sup> - 4t, the average rate from t = 1 to t = 4 is [h(4) - h(1)]/3 = [0 - (-3)]/3 = 1.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Guided Practice</h2>
<ol>
  <li>If f(x) = 3x + 1 and g(x) = x<sup>2</sup>, find f(g(2)) and g(f(2)).</li>
  <li>State the domain of (f/g)(x) if f(x) = √(x + 1) and g(x) = x - 2.</li>
  <li>Find the average rate of change of x<sup>2</sup> - 3x from x = 1 to x = 5.</li>
  <li>Estimate the instantaneous rate of x<sup>2</sup> at x = 3 using x = 2.99 and x = 3.01.</li>
  <li>Explain in context what a negative average rate of change means.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>f(g(2)) = 13; g(f(2)) = 49.</li><li>x ≥ -1 and x ≠ 2.</li><li>3.</li><li>Approximately 6.</li><li>The output decreases, on average, per unit increase in the input.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Function composition', 'text' => 'If f(x) = x + 2 and g(x) = 3x, what is (f ◦ g)(x)?', 'answers' => ['3x + 2', '3x + 6', 'x + 6', '3x²'], 'correct' => 0, 'feedback' => 'Substitute g(x) into f: f(3x) = 3x + 2.'],
                ['name' => 'Average rate formula', 'text' => 'Which expression gives average rate of change from x = a to x = b?', 'answers' => ['[f(b) - f(a)]/(b - a)', '[f(b) + f(a)]/(b + a)', 'f(b)/f(a)', '(b - a)/[f(b) - f(a)]'], 'correct' => 0, 'feedback' => 'Average rate is change in output divided by change in input.'],
                ['name' => 'Composition order', 'text' => 'In f(g(x)), which function is evaluated first?', 'answers' => ['g', 'f', 'Both at the same time', 'Neither'], 'correct' => 0, 'feedback' => 'The inside function g produces the input for f.'],
            ],
            'assignment' => <<<'HTML'
<h2>Rates of Change Application</h2>
<p>Choose a function that models distance, cost, volume, population, or another measurable quantity.</p>
<ol>
  <li>Define the function, variables, units, and a reasonable domain.</li>
  <li>Calculate average rates over three intervals and interpret each result.</li>
  <li>Estimate one instantaneous rate using two small surrounding intervals.</li>
  <li>Represent the situation with a graph and mark the relevant secant slopes.</li>
  <li>Explain one decision that could be informed by the rates you found.</li>
</ol>
HTML,
        ],
        [
            'section' => 7,
            'title' => 'Review and Final Preparation',
            'summary' => 'Cumulative review, mixed practice, error analysis, and final assessment preparation.',
            'lesson' => <<<'HTML'
<h2>Review and Final Preparation</h2>
<p>Successful final preparation is active: retrieve methods from memory, solve mixed problems, analyze errors, and explain why each method applies.</p>
<h3>Five-part review cycle</h3>
<ol>
  <li><strong>Classify:</strong> identify the function family and important restrictions.</li>
  <li><strong>Represent:</strong> connect algebraic, graphical, and numerical forms.</li>
  <li><strong>Solve:</strong> choose a method and show justified steps.</li>
  <li><strong>Verify:</strong> substitute, check domains, and assess reasonableness.</li>
  <li><strong>Communicate:</strong> state the conclusion with correct notation and units.</li>
</ol>
<h3>Exam preparation</h3>
<p>Build a one-page concept map from memory, then correct it using course notes. Complete timed mixed sets. Maintain an error log with the original error, the corrected reasoning, and a similar problem that you can now solve.</p>
HTML,
            'practice' => <<<'HTML'
<h2>Mixed Practice Set</h2>
<ol>
  <li>Find the inverse of f(x) = 4x + 9.</li>
  <li>Factor x<sup>3</sup> - 4x and state all zeros.</li>
  <li>Identify every restriction and asymptote of (x + 2)/(x<sup>2</sup> - 1).</li>
  <li>Solve 4<sup>x</sup> = 32.</li>
  <li>Solve sin x = -1/2 on 0 ≤ x &lt; 2π.</li>
  <li>Find the average rate of change of √x from x = 1 to x = 9.</li>
</ol>
<details><summary>Answer check</summary><ol>
  <li>f<sup>-1</sup>(x) = (x - 9)/4.</li><li>x(x - 2)(x + 2); zeros -2, 0, 2.</li><li>x ≠ -1, 1; vertical asymptotes x = -1 and x = 1; horizontal asymptote y = 0.</li><li>x = 5/2.</li><li>7π/6 and 11π/6.</li><li>1/4.</li>
</ol></details>
HTML,
            'quiz' => [
                ['name' => 'Review inverse', 'text' => 'What is the inverse of f(x) = 2x + 6?', 'answers' => ['(x - 6)/2', '2x - 6', '(x + 6)/2', '6 - 2x'], 'correct' => 0, 'feedback' => 'Interchange x and y, then isolate y.'],
                ['name' => 'Review polynomial zero', 'text' => 'Which value is a zero of P(x) = x³ - 9x?', 'answers' => ['3', '1', '2', '4'], 'correct' => 0, 'feedback' => 'Factor P(x) as x(x - 3)(x + 3).'],
                ['name' => 'Review rational asymptote', 'text' => 'What is the vertical asymptote of f(x) = 1/(x + 7)?', 'answers' => ['x = -7', 'y = -7', 'x = 7', 'y = 0'], 'correct' => 0, 'feedback' => 'Set the denominator equal to zero.'],
                ['name' => 'Review logarithm', 'text' => 'Solve log₂(x) = 5.', 'answers' => ['x = 32', 'x = 10', 'x = 7', 'x = 25'], 'correct' => 0, 'feedback' => 'Rewrite in exponential form: x = 2⁵.'],
                ['name' => 'Review trigonometry', 'text' => 'What is sin(π/6)?', 'answers' => ['1/2', '√2/2', '√3/2', '1'], 'correct' => 0, 'feedback' => 'Use the unit-circle value at 30 degrees.'],
                ['name' => 'Review rate of change', 'text' => 'What does average rate of change represent graphically?', 'answers' => ['The slope of a secant line', 'The y-intercept', 'The area under a curve', 'A vertical asymptote'], 'correct' => 0, 'feedback' => 'It compares two points on the graph.'],
            ],
            'assignment' => <<<'HTML'
<h2>Final Readiness Portfolio</h2>
<p>Submit a final portfolio that demonstrates readiness across the course.</p>
<ol>
  <li>Choose one representative problem from each of Units 1 through 6 and provide a complete corrected solution.</li>
  <li>For three problems, explain a likely misconception and how to avoid it.</li>
  <li>Create a concept map showing at least eight connections among function families, transformations, restrictions, inverses, equations, and rates of change.</li>
  <li>Write a short study plan that identifies two strengths, two priority gaps, and specific next actions.</li>
</ol>
<p>Your submission should be organized, original, mathematically accurate, and easy for another learner to follow.</p>
HTML,
        ],
    ],
];
