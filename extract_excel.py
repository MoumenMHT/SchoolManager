import pandas as pd
import json
import sys
import glob

files = glob.glob(r'C:\Users\issam\Downloads\*.xlsx')
target_file = next((f for f in files if 'قائمة الإسمية' in f), None)

if not target_file:
    print('Error: Target file not found', file=sys.stderr)
    sys.exit(1)

try:
    xl = pd.ExcelFile(target_file)
    data = {}
    for sheet_name in xl.sheet_names:
        df = xl.parse(sheet_name)
        def serialize(obj):
            if isinstance(obj, (pd.Timestamp,)):
                return obj.isoformat()
            return obj
        
        records = df.where(pd.notnull(df), None).to_dict(orient='records')
        # Apply serialization to each cell
        records = [[serialize(val) for val in row.values()] for _, row in df.iterrows()]
        data[sheet_name] = df.where(pd.notnull(df), None).apply(lambda x: x.map(serialize)).to_dict(orient='records')
    print(json.dumps(data, ensure_ascii=False))
except Exception as e:
    print(f'Error: {str(e)}', file=sys.stderr)
