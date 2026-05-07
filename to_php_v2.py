import json
import pandas as pd
import datetime

with open('excel_data.json', 'r', encoding='utf-8') as f:
    raw_data = json.load(f)

def php_val(v):
    if v is None: return 'null'
    s = str(v).replace("'", "\\'")
    return "'" + s + "'"

# Process Students
students = []
df_s = raw_data.get('قوائم التلاميد', [])
for row in df_s:
    # Check if this is a data row (header rows have strings in Unnamed: 0, student rows have numbers)
    if isinstance(row.get('Unnamed: 0'), (int, float)) and row.get('Unnamed: 0') is not None:
        students.append({
            'reg_no': row.get('Unnamed: 1'),
            'last_name': row.get('قائمة تلاميذ قسم السنة الرابعة ابتدائي 1 '),
            'first_name': row.get('Unnamed: 3'),
            'father_name': row.get('Unnamed: 4'),
            'class': row.get('Unnamed: 5'),
            'birth_date': row.get('Unnamed: 6'),
            'birth_place': row.get('Unnamed: 7'),
            'address': row.get('Unnamed: 8')
        })

# Process Teachers
teachers = []
df_t = raw_data.get('قوائم الاساتدة', [])
# Teachers are in 2 columns groups: [1, 2] and [4, 5]
for row in df_t:
    # Group 1 (Primary)
    if row.get('Unnamed: 1') and row.get('Unnamed: 2') and row.get('Unnamed: 1') != 'المادة ':
        teachers.append({'name': row['Unnamed: 2'], 'subject': row['Unnamed: 1'], 'band': 'primary'})
    # Group 2 (CEM)
    if row.get('Unnamed: 4') and row.get('Unnamed: 5') and row.get('Unnamed: 4') != 'المادة ':
        teachers.append({'name': row['Unnamed: 5'], 'subject': row['Unnamed: 4'], 'band': 'cem'})

# Export to PHP format
php_students = '[\n'
for s in students:
    line = "    ["
    line += "'reg_no' => " + php_val(s['reg_no']) + ", "
    line += "'last_name' => " + php_val(s['last_name']) + ", "
    line += "'first_name' => " + php_val(s['first_name']) + ", "
    line += "'father_name' => " + php_val(s['father_name']) + ", "
    line += "'class' => " + php_val(s['class']) + ", "
    line += "'birth_date' => " + php_val(s['birth_date']) + ", "
    line += "'birth_place' => " + php_val(s['birth_place']) + ", "
    line += "'address' => " + php_val(s['address'])
    line += "],\n"
    php_students += line
php_students += ']'

php_teachers = '[\n'
for t in teachers:
    line = "    ["
    line += "'name' => " + php_val(t['name']) + ", "
    line += "'subject' => " + php_val(t['subject']) + ", "
    line += "'band' => " + php_val(t['band'])
    line += "],\n"
    php_teachers += line
php_teachers += ']'

with open('students_php.txt', 'w', encoding='utf-8') as f: f.write(php_students)
with open('teachers_php.txt', 'w', encoding='utf-8') as f: f.write(php_teachers)
print("PHP data generated in students_php.txt and teachers_php.txt")